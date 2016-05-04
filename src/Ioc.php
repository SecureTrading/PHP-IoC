<?php

namespace Securetrading\Ioc;

class Ioc implements IocInterface {
  protected $_types = array();

  protected $_resolving = array();

  protected $_beforeInstantiationCallbacks = array();

  protected $_afterInstantiationCallbacks = array();

  protected $_singletons = array();

  protected $_options = array();

  public static function instance() {
    return new static();
  }
  
  public function before($key, $callable = null) {
    $this->_addToCallbacks($this->_beforeInstantiationCallbacks, $key, $callable);
    return $this;
  }

  public function after($key, $callable = null) {
    $this->_addToCallbacks($this->_afterInstantiationCallbacks, $key, $callable);
    return $this;
  }

  public function set($alias, $value) {
    if (!is_string($alias)) {
      throw new IocException('Alias must be a string.', IocException::CODE_INVALID_ALIAS);
    }

    if (is_string($value) && !class_exists($value)) {
      throw new IocException(sprintf('Class "%s" does not exist.', $value), IocException::CODE_INVALID_CLASS);
    }

    if (!is_string($value) && !is_callable($value)) {
      throw new IocException(sprintf('Invalid type value for alias "%s".', $alias), IocException::CODE_INVALID_TYPE);
    }
  
    $this->_types[$alias] = $value;
    return $this;
  }

  public function has($type) {
    return array_key_exists($type, $this->_types);
  }

  public function get($alias, array $params = array()) {
    $this->_assertNotResolving($alias);
    $this->_setResolving($alias, true);
    $this->_beforeInstantiation($alias, $params);
    
    if (array_key_exists($alias, $this->_types)) {
      $typeValue = $this->_types[$alias];
      if (is_callable($typeValue)) {
	$instance = call_user_func($typeValue, $this, $alias, $params);
      }
      else {
	$instance = $this->_newInstance($typeValue, $params);
      }
    }
    else {
      if (class_exists($alias)) {
	$instance = $this->_newInstance($alias, $params);
      }
      else {
	throw new IocException(sprintf('Type "%s" does not exist.', $alias), IocException::CODE_MISSING_ALIAS);
      }
    }
    
    $this->_afterInstantiation($instance, $alias, $params);
    $this->_setResolving($alias, false);

    return $instance;
  }

  public function create($alias, array $params = array()) { // Note - An alias for $this->get().
    return $this->get($alias, $params);
  }

  public function getSingleton($alias, array $params = array()) {
    if (array_key_exists($alias, $this->_singletons)) {
      $instance = $this->_singletons[$alias];
    }
    else {
      $instance = $this->get($alias, $params);
      $this->_singletons[$alias] = $instance;
    }
    return $instance;
  }

  public function setOption($key, $value) {
    $this->_options[$key] = $value;
    return $this;
  }

  public function getOption($key) {
    if (!array_key_exists($key, $this->_options)) {
      throw new IocException(sprintf('Option "%s" required.', $key), IocException::CODE_OPTION_MISSING);
    }
    return $this->_options[$key];
  }

  public function hasOption($key) {
    return array_key_exists($key, $this->_options);
  }

  public function hasParameter($key, array $params) {
    return array_key_exists($key, $params);
  }

  public function getParameter($key, array $params, $default = null) {
    if (!array_key_exists($key, $params)) {
      if (func_num_args() < 3) {
	throw new IocException(sprintf('Parameter "%s" required.', $key), IocException::CODE_PARAM_MISSING);
      }
      $returnValue = $default;
    }
    else {
      $returnValue = $params[$key];
    }
    return $returnValue;
  }

  protected function _assertNotResolving($alias) {
    if (array_key_exists($alias, $this->_resolving) && $this->_resolving[$alias]) {
      throw new IocException(sprintf('Circular dependency when resolving "%s".', $alias), IocException::CODE_CIRCULAR_RESOLUTION);
    }
  }

  protected function _setResolving($alias, $bool) {
    $this->_resolving[$alias] = (bool) $bool;
  }

  protected function _beforeInstantiation($alias, &$params) {
    $callbacks = $this->_getCallbacksForKey($alias, $this->_beforeInstantiationCallbacks);
    foreach($callbacks as $callback) {
      call_user_func_array($callback, array($alias, &$params));
    }
  }

  protected function _afterInstantiation($instance, $alias, $params) {
    $callbacks = $this->_getCallbacksForKey($alias, $this->_afterInstantiationCallbacks);
    foreach($callbacks as $callback) {
      call_user_func_array($callback, array($this, $instance, $alias, &$params));
    }
  }

  protected function _getCallbacksForKey($key, $callbackArray) {
    $keys = (array) $key;
    if ($key !== '*') {
      $keys = array_merge(array('*'), $keys);
    }
    
    $callbacks = array();
    foreach($keys as $key) {
      if (array_key_exists($key, $callbackArray)) {
	foreach($callbackArray[$key] as $callable) {
	  $callbacks[] = $callable;
	}
      }
    }
    return $callbacks;
  }

  protected function _newInstance($type, $params) {
    $reflectionClass = new \ReflectionClass($type);
    if (!$reflectionClass->isInstantiable()) {
      throw new IocException(sprintf('Type "%s" is not instantiable.', $type), IocException::CODE_TYPE_NOT_INSTANTIABLE);
    }
    $instance = $reflectionClass->newInstanceArgs($params);
    return $instance;
  }

  protected function _addToCallbacks(&$callbackArray, $key, $callable = null) {
    if (null === $callable) {
      $callable = $key;
      $key = '*';
    }
    $callbackArray[$key][] = $callable;    
  }
}