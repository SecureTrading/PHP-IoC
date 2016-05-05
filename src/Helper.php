<?php

namespace Securetrading\Ioc;

class Helper {
  protected $_ioc;

  protected $_vendorDirs = array();

  protected $_etcDirs = array();

  protected $_definitionFiles = array();

  protected $_packageDefinitionFiles = array();

  protected $_packageDefinitions = array();

  protected $_loadedPackageNames = array();

  public static function instance(IocInterface $ioc = null) {
    if (!$ioc) {
      $ioc = Ioc::instance();
    }
    $instance = new static($ioc);
    $instance->setIoc($ioc);
    return $instance;
  }

  public function setIoc(IocInterface $ioc) {
    $this->_ioc = $ioc;
    return $this;
  }

  public function getIoc() {
    if ($this->_ioc === null) {
      throw new HelperException('IoC instance not set.', HelperException::CODE_IOC_INSTANCE_NOT_SET);
    }
    return $this->_ioc;
  }

  public function addVendorDirs($vendorDirs) {
    if (!is_array($vendorDirs)) {
      $vendorDirs = array($vendorDirs);
    }
    foreach($vendorDirs as $vendorDir) {
      $this->_vendorDirs[$vendorDir] = true;
    }
    return $this;
  }

  public function addEtcDirs($etcDirs) {
    if (!is_array($etcDirs)) {
      $etcDirs = array($etcDirs);
    }
    foreach($etcDirs as $etcDir) {
      $this->_etcDirs[$etcDir] = true;
    }
    return $this;
  }

  public function addDefinitionFiles($definitionFiles) {
    if (!is_array($definitionFiles)) {
      $definitionFiles = array($definitionFiles);
    }
    foreach($definitionFiles as $definitionFile) {
      $this->_definitionFiles[$definitionFile] = true;
    }
    return $this;
  }

  public function loadPackages(array $packageNames) {
    foreach($packageNames as $packageName) {
 
      $this->loadPackage($packageName);
    }
    return $this;
  }
  
  public function loadPackage($packageName) {
    if (!$this->_packageDefinitions) {
      $this->_findAndSortPackageDefinitions();
      $this->_readPackageDefinitions($this->_packageDefinitionFiles);
    }
    $this->_loadPackage($packageName);
    return $this;
  }

  public function getPackageDefinitionFiles() {
    return $this->_packageDefinitionFiles;
  }
  
  public function getPackageDefinitions() {
    return $this->_packageDefinitions;
  }

  public function getLoadedPackageNames() {
    return array_keys($this->_loadedPackageNames);
  }

  protected function _findAndSortPackageDefinitions() {
    $files = array();

    foreach(array_keys($this->_vendorDirs) as $vendorDir) {
      foreach (scandir($vendorDir) as $filename) { 
	$packageDir = rtrim($vendorDir, '//') . DIRECTORY_SEPARATOR . $filename;

	if (in_array($filename, array('.', '..')) || !is_dir($packageDir)) {
	  continue;
	}

	$etcDir = $packageDir . DIRECTORY_SEPARATOR . 'etc';
	$this->addEtcDirs($etcDir);
      }
    }
    
    foreach(array_keys($this->_etcDirs) as $etcDir) {
      if (!file_exists($etcDir) || !is_dir($etcDir)) {
	continue;
      }

      $etcDirContents = scandir($etcDir);
      
      foreach($etcDirContents as $filename) {
	$filepath = $etcDir . DIRECTORY_SEPARATOR . $filename;
	if (is_file($filepath) && is_readable($filepath) && fnmatch("*_ioc.php", $filename)) {
	  $files[] = $etcDir . DIRECTORY_SEPARATOR . $filename;
	}	
      }
    }

    foreach(array_keys($this->_definitionFiles) as $definitionFile) {
      if (is_file($definitionFile) && is_readable($definitionFile)) {
	$files[] = $definitionFile;
      }
    }
    
    uasort($files, function($a, $b) {
      return strcmp(basename($a), basename($b));
    });
    
    $this->_packageDefinitionFiles = array_values($files);
  }

  protected function _readPackageDefinitions(array $files) {
    foreach($files as $file) {
      $packageDefinitions = require $file;
      
      if (!is_array($packageDefinitions)) {
	throw new HelperException(sprintf('The package definitions file "%s" must return an array.', $file), HelperException::CODE_PACKAGE_FILE_NOT_ARRAY);
      }
      
      foreach($packageDefinitions as $packageName => $packageDefinition) {
	if (isset($packageDefinition['definitions']) && !is_array($packageDefinition['definitions'])) {
	  throw new HelperException(sprintf('The definitions in package definition "%s" from "%s" must be an array".', $packageName, $file), HelperException::CODE_PACKAGE_FILE_BAD_DEFINITIONS);
	}

	if (isset($packageDefinition['dependencies']) && !is_array($packageDefinition['dependencies'])) {
	  throw new HelperException(sprintf('The dependencies in package definition "%s" from "%s" must be an array".', $packageName, $file), HelperException::CODE_PACKAGE_FILE_BAD_DEPENDENCIES);
	}

	if (isset($packageDefinition['onload']) && !is_callable($packageDefinition['onload'])) {
	  throw new HelperException(sprintf('The onload callback in package definition "%s" from "%s" must be callable".', $packageName, $file), HelperException::CODE_PACKAGE_FILE_BAD_ONLOAD);
	}

	$this->_packageDefinitions = array_merge($this->_packageDefinitions, $packageDefinitions);
      }
    }
  }

  protected function _loadPackage($packageName) {
    if (!isset($this->_packageDefinitions[$packageName])) {
      throw new HelperException(sprintf('The package definition "%s" could not be found.', $packageName), HelperException::CODE_PACKAGE_DEFINITION_NOT_FOUND);
    }

    if (isset($this->_loadedPackageNames[$packageName])) {
      return;
    }
    
    if (isset($this->_packageDefinitions[$packageName]['definitions'])) {
      foreach($this->_packageDefinitions[$packageName]['definitions'] as $key => $value) {
	$this->getIoc()->set($key, $value);
      }
    }

    if (isset($this->_packageDefinitions[$packageName]['onload'])) {
      call_user_func($this->_packageDefinitions[$packageName]['onload'], $this->getIoc());
    }

    $this->_loadedPackageNames[$packageName] = true;

    if (isset($this->_packageDefinitions[$packageName]['dependencies'])) {
      foreach($this->_packageDefinitions[$packageName]['dependencies'] as $dependencyPackageName) {
        $this->_loadPackage($dependencyPackageName);
      }
    }
  }
}