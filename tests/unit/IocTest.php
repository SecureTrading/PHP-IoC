<?php

namespace Securetrading\Ioc\Tests\Unit;

use org\bovigo\vfs\vfsStream;

require_once(__DIR__ . '/helpers/NotInstantiable.php');
require_once(__DIR__ . '/helpers/TwoParamConstructor.php');
require_once(__DIR__ . '/helpers/EmptyConstructor.php');

class IocTest extends \Securetrading\Unittest\UnittestAbstract {
  protected function _getBeforeCallbackMock($alias, $params, $shouldBeFired) {
    $methodName = 'myCallback';
    $called = $shouldBeFired ? $this->once() : $this->never();

    $mock = $this->getMockBuilder('\stdClass')
          ->setMethods(array($methodName))
          ->getMock();
    
    $mock
      ->expects($called)
      ->method($methodName)
      ->with($this->equalTo($alias), $this->equalTo($params))
    ;

    return array($mock, $methodName);
  }

  protected function _getAfterCallbackMock($instance, $alias, $params, $shouldBeFired) {
    $methodName = 'myCallback';
    $called = $shouldBeFired ? $this->once() : $this->never();

    $mock = $this->getMockBuilder('\stdClass')
          ->setMethods(array($methodName))
          ->getMock();
    
    $mock
      ->expects($called)
      ->method($methodName)
      ->with(
	$this->identicalTo($this->_ioc),
	$this->callback(function($subject) use ($instance) { return $subject instanceof $instance; }),
	$this->equalTo($alias),
	$this->equalTo($params)
      )
    ;

    return array($mock, $methodName);
  }

  public function setUp() : void {
    $this->_ioc = new \Securetrading\Ioc\Ioc();
    $this->_rootDir = vfsStream::setup('rootTestDirectory');
  }

  public function tearDown() : void {
    foreach($this->_rootDir->getChildren() as $child) {
      $this->_rootDir->removeChild($child->getName());
    }
  }

  /**
   * 
   */
  public function testInstance() {
    $returnValue = \Securetrading\Ioc\Ioc::instance();
    $this->assertInstanceOf("\Securetrading\Ioc\Ioc", $returnValue);
  }

  /**
   * 
   */
  public function testBefore_ReturnValue() {
    $returnValue = $this->_ioc->before(function($alias, $params) { });
    $this->assertSame($this->_ioc, $returnValue);
  }
  
  /**
   * 
   */
  public function testAfter_ReturnValue() {
    $returnValue = $this->_ioc->after(function($instance, $alias, $params) { });
    $this->assertSame($this->_ioc, $returnValue);
  }

  /**
   * 
   */
  public function testSet_ReturnValue() {
    $returnValue = $this->_ioc->set('aliasToAClassThatExists', __CLASS__);
    $this->assertSame($this->_ioc, $returnValue);
  }
  
  /**
   * 
   */
  public function testSet_ThrowsInvalidAlias() {
    $this->expectException(\Securetrading\Ioc\IocException::class);
    $this->expectExceptionCode(\Securetrading\Ioc\IocException::CODE_INVALID_ALIAS);
    
    $this->_ioc->set(function() { }, 3);
  }

  /**
   * 
   */
  public function testSet_ThrowsInvalidClass() {
    $this->expectException(\Securetrading\Ioc\IocException::class);
    $this->expectExceptionCode(\Securetrading\Ioc\IocException::CODE_INVALID_CLASS);
    
    $this->_ioc->set('myAlias', '\Class\That\Does\Not\Exist');
  }

  /**
   * 
   */
  public function testSet_ThrowsInvalidType() {
    $this->expectException(\Securetrading\Ioc\IocException::class);
    $this->expectExceptionCode(\Securetrading\Ioc\IocException::CODE_INVALID_TYPE);
    
    $this->_ioc->set('myAlias', 3);
  }

  /**
   *
   */
  public function testHas() {
    $this->_ioc->set('myAlias', __CLASS__);
    $this->assertEquals(false, $this->_ioc->has('unsetAlias'));
    $this->assertEquals(true, $this->_ioc->has('myAlias'));
  }
  
  /**
   * 
   */
  public function testGet_OnCircularResolution_ThrowsException() {
    $this->expectException(\Securetrading\Ioc\IocException::class);
    $this->expectExceptionCode(\Securetrading\Ioc\IocException::CODE_CIRCULAR_RESOLUTION);
    
    $this->_ioc->set('myAlias', function(\Securetrading\Ioc\IocInterface $ioc, $alias, $params) {
      return $ioc->get('myAlias');
    });
    $this->_ioc->get('myAlias');
  }

  /**
   * 
   */
  public function testGet_OnMissingAlias_ThrowsException() {
    $this->expectException(\Securetrading\Ioc\IocException::class);
    $this->expectExceptionCode(\Securetrading\Ioc\IocException::CODE_MISSING_ALIAS);
    
    $this->_ioc->get('thisAliasDoesNotExist');
  }

  /**
   *
   */
  public function testGet_FromClassName() {
    $returnValue = $this->_ioc->get('\stdClass');
    $this->assertInstanceOf('\stdClass', $returnValue);
  }

  /**
   * 
   */
  public function testGet_FromStringType_WithNoParams_ReturnValueIsCorrect() {
    $this->_ioc->set('core_stdclass', '\stdClass');
    $actualReturnValue = $this->_ioc->get('core_stdclass');
    $this->assertInstanceOf('\stdClass', $actualReturnValue);
  }

  /**
   * 
   */
  public function testGet_FromStringType_WithParams_ReturnValueAndParamsAreCorrect() {
    $this->_ioc->set('alias', '\TwoParamConstructor');

    $actualReturnValue = $this->_ioc->get('alias', array('param1', 'param2'));

    $this->assertInstanceOf('\TwoParamConstructor', $actualReturnValue);
    $this->assertEquals('param1', $actualReturnValue->getParam1());
    $this->assertEquals('param2', $actualReturnValue->getParam2());
  }

  /**
   * 
   */
  public function testGet_FromStringType_TypeIsNotInstantiable() {
    $this->expectException(\Securetrading\Ioc\IocException::class);
    $this->expectExceptionCode(\Securetrading\Ioc\IocException::CODE_TYPE_NOT_INSTANTIABLE);
    
    $this->_ioc->set('not_instantiable', '\NotInstantiable');
    $this->_ioc->get('not_instantiable');
  }

  /**
   *
   */
  public function testGet_UsingFunctionCallback_ReturnValueIsCorrect() {
    $this->_ioc->set('alias', function(\Securetrading\Ioc\IocInterface $ioc, $alias, $params) { return new \stdClass; });
    $actualReturnValue = $this->_ioc->get('alias');
    $this->assertInstanceOf('\stdClass', $actualReturnValue);
  }

  /**
   * 
   */
  public function testGet_UsingFunctionCallback_ParamsAreCorrect() {
    $that = $this;
    $callback = function(\Securetrading\Ioc\IocInterface $ioc, $alias, array $params) use ($that) {
      $that->assertSame($that->_ioc, $ioc);
      $that->assertEquals('myalias', $alias);
      $that->assertEquals(array('key_1' => 'value_1'), $params);
    };
    $this->_ioc->set('myalias', $callback);
    $this->_ioc->get('myalias', array('key_1' => 'value_1'));
  }

  /**
   * 
   */
  public function testGet_UsingMethodCallback_ParamsAndReturnValueAreCorrect() {
    $mock = $this->getMockBuilder('\stdClass')
          ->setMethods(array('getNewObject'))
          ->getMock();

    $mock
      ->expects($this->once())
      ->method('getNewObject')
      ->with($this->identicalTo($this->_ioc), $this->equalTo('alias'), $this->equalTo(array()))
      ->will($this->returnValue(new \stdClass()))
    ;

    $this->_ioc->set('alias', array($mock, 'getNewObject'));

    $actualReturnValue = $this->_ioc->get('alias');

    $this->assertInstanceOf("\stdClass", $actualReturnValue);
  }
  
  /**
   * 
   */
  public function testGet_UsingBeforeCallback_AttachedToEveryAlias_CallbackShouldBeRun() {
    $this->_ioc->before('*', $this->_getBeforeCallbackMock('myalias', ['p1', 'p2'], true));
    $this->_ioc->set('myalias', '\EmptyConstructor');
    $this->_ioc->get('myalias', ['p1', 'p2']);
  }

  /**
   * 
   */
  public function testGet_UsingBeforeCallback_AttachedToOneAlias_CallbackShouldBeRun() {
    $this->_ioc->before('myalias', $this->_getBeforeCallbackMock('myalias', ['p1', 'p2'], true));
    $this->_ioc->set('myalias', '\EmptyConstructor');
    $this->_ioc->get('myalias', ['p1', 'p2']);
  }

  /**
   * 
   */
  public function testGet_UsingBeforeCallback_AttachedToEveryAliasByDefault_CallbackShouldBeRun() {
    $this->_ioc->before($this->_getBeforeCallbackMock('myalias', ['p1', 'p2'], true));
    $this->_ioc->set('myalias', '\EmptyConstructor');
    $this->_ioc->get('myalias', ['p1', 'p2']);
  }

  /**
   * 
   */
  public function testGet_UsingBeforeCallback_AttachedToADifferentAlias_CallbackShouldNotBeRun() {
    $this->_ioc->before('anotheralias', $this->_getBeforeCallbackMock('myalias', ['p1', 'p2'], false));
    $this->_ioc->set('myalias', '\EmptyConstructor');
    $this->_ioc->get('myalias', ['p1', 'p2']);
  }

  /**
   * 
   */
  public function testGet_UsingBeforeCallback_CanModifyParamsThatArePassedToConstructor() {
    $that = $this;

    $beforeCallback = function($alias, array &$params) {
      $params = array('new_key' => 'new_value');
    };

    $newInstanceCallback = function(\Securetrading\Ioc\IocInterface $ioc, $alias, array $params) use ($that) {
      $that->assertEquals(array('new_key' => 'new_value'), $params);
    };

    $this->_ioc->before('myalias', $beforeCallback);
    $this->_ioc->set('myalias', $newInstanceCallback);
    $this->_ioc->get('myalias', array('key' => 'value'));
  }

  /**
   * 
   */
  public function testGet_UsingBeforeCallback_CallbacksRunInTheExpectedOrder() {
    $that = $this;
    $i = 0;

    $getCallback = function($expectedCallOrder) use (&$i, $that) {
      return function($alias, $params) use ($that, &$i, $expectedCallOrder) {
	$that->assertEquals($expectedCallOrder, $i);
	$i++;
      };
    };

    $this->_ioc->before('myalias', $getCallback(2));
    $this->_ioc->before('myalias', $getCallback(3));
    $this->_ioc->before($getCallback(0));
    $this->_ioc->before('*', $getCallback(1));

    $this->_ioc->set('myalias', '\stdClass');
    $this->_ioc->get('myalias');
  }

  /**
   * 
   */
  public function testGet_UsingAfterCallback_AttachedToEveryAlias_CallbackShouldBeRun() {
    $this->_ioc->after('*', $this->_getAfterCallbackMock('\EmptyConstructor', 'myalias', ['p1', 'p2'], true));
    $this->_ioc->set('myalias', '\EmptyConstructor');
    $this->_ioc->get('myalias', ['p1', 'p2']);
  }

  /**
   * 
   */
  public function testGet_UsingAfterCallback_AttachedToOneAlias_CallbackShouldBeRun() {
    $this->_ioc->after('myalias', $this->_getAfterCallbackMock('\EmptyConstructor', 'myalias', ['p1', 'p2'], true));
    $this->_ioc->set('myalias', '\EmptyConstructor');
    $this->_ioc->get('myalias', ['p1', 'p2']);
  }

  /**
   * 
   */
  public function testGet_UsingAfterCallback_AttachedToEveryAliasByDefault_CallbackShouldBeRun() {
    $this->_ioc->after($this->_getAfterCallbackMock('\EmptyConstructor', 'myalias', ['p1', 'p2'], true));
    $this->_ioc->set('myalias', '\EmptyConstructor');
    $this->_ioc->get('myalias', ['p1', 'p2']);
  }

  /**
   * 
   */
  public function testGet_UsingAfterCallback_AttachedToADifferentAlias_CallbackShouldNotBeRun() {
    $this->_ioc->after('anotheralias', $this->_getAfterCallbackMock('\EmptyConstructor', 'myalias', ['p1', 'p2'], false));
    $this->_ioc->set('myalias', '\EmptyConstructor');
    $this->_ioc->get('myalias', ['p1', 'p2']);
  }

  /**
   * 
   */
  public function testGet_UsingAfterCallback_CallbacksRunInTheExpectedOrder() {
    $that = $this;
    $i = 0;

    $getCallback = function($expectedCallOrder) use (&$i, $that) {
      return function(\Securetrading\Ioc\IocInterface $ioc, $instance, $alias, $params) use ($that, &$i, $expectedCallOrder) {
	$that->assertEquals($expectedCallOrder, $i);
	$i++;
      };
    };

    $this->_ioc->after('myalias', $getCallback(2));
    $this->_ioc->after('myalias', $getCallback(3));
    $this->_ioc->after($getCallback(0));
    $this->_ioc->after('*', $getCallback(1));

    $this->_ioc->set('myalias', '\stdClass');
    $this->_ioc->get('myalias');
  }

  /**
   *
   */
  public function testGet_UsingAfterCallback_NewInstanceCallbackParams_ModifiedByReference() {
    $that = $this;

    $newInstanceCallback = function(\Securetrading\Ioc\IocInterface $ioc, $alias, array $params) use ($that) {
      $that->assertEquals(array('key' => 'value'), $params);
      $params['new_key'] = 'new_value';
    };

    $afterCallback = function(\Securetrading\Ioc\IocInterface $ioc, $instance, $alias, $params) use ($that) {
      $that->assertEquals(array('key' => 'value', 'new_key' => 'new_value'), $params);
    };

    $this->_ioc->set('myalias', $newInstanceCallback);
    $this->_ioc->after('after', $afterCallback);
    $this->_ioc->get('myalias', array('key' => 'value'));
  }

  /**
   * 
   */
  public function testCreate() {
    $mockedIoc = $this->createMock('\Securetrading\Ioc\Ioc', array('get'));
    $mockedIoc
      ->expects($this->once())
      ->method('get')
      ->with($this->equalTo('myAlias'), $this->equalTo(array('k' => 'v')))
      ->willReturn('return_value')
    ;

    $mockedIoc->set('myAlias', '\stdClass');
    $returnValue = $mockedIoc->get('myAlias', array('k' => 'v'));

    $this->assertEquals('return_value', $returnValue);
  }

  /**
   *
   */
  public function testGetSingleton() {
    $this->_ioc->set('alias1', '\stdClass');
    $this->_ioc->set('alias2', '\stdClass');
    $instance1 = $this->_ioc->getSingleton('alias1');
    $instance2 = $this->_ioc->getSingleton('alias1');
    $instance3 = $this->_ioc->getSingleton('alias2');
    $this->assertSame($instance1, $instance2);
    $this->assertNotSame($instance1, $instance3);
  }

  /**
   * 
   */
  public function testSetOption_CorrectReturnValue() {
    $returnValue = $this->_ioc->setOption('key', 'value');
    $this->assertSame($this->_ioc, $returnValue);
  }

  /**
   * 
   */
  public function testGetOption_CorrectReturnValue() {
    $this->_ioc->setOption('key', 'value');
    $returnValue = $this->_ioc->getOption('key');
    $this->assertEquals('value', $returnValue);
  }

  /**
   * 
   */
  public function testGetOption_KeyDoesNotExist_ThrowsException() {
    $this->expectException(\Securetrading\Ioc\IocException::class);
    $this->expectExceptionCode(\Securetrading\Ioc\IocException::CODE_OPTION_MISSING);
    
    $this->_ioc->getOption('key_that_does_not_exist');
  }

  /**
   * 
   */
  public function testHasOption() {
    $this->_ioc->setOption('a', 'b');
    $this->assertEquals(true, $this->_ioc->hasOption('a'));
    $this->assertEquals(false, $this->_ioc->hasOption('b'));
  }

  /**
   * @dataProvider providerHasParameter
   */
  public function testHasParameter(array $params, $key, $expectedReturnValue) {
    $actualReturnValue = $this->_ioc->hasParameter($key, $params);
    $this->assertEquals($expectedReturnValue, $actualReturnValue);
  }

  public function providerHasParameter() {
    $this->_addDataSet(array('a' => 'a', 'b' => 'b'), 'a', true);
    $this->_addDataSet(array('a' => 'a', 'b' => 'b'), 'c', false);
    return $this->_getDataSets();
  }

  /**
   * 
   */
  public function testGetParameter_UsingKeyThatExists() {
    $params = array('key' => 'value');
    $returnValue = $this->_ioc->getParameter('key', $params);
    $this->assertEquals('value', $returnValue);
  }

  /**
   * 
   */
  public function testGetParameter_UsingKeyThatDoesNotExist() {
    $this->expectException(\Securetrading\Ioc\IocException::class);
    $this->expectExceptionCode(\Securetrading\Ioc\IocException::CODE_PARAM_MISSING);
    
    $this->_ioc->getParameter('key_that_has_not_been_set', array());
  }

  /**
   * 
   */
  public function testGetParameter_UsingKeyThatDoesNotExist_WhenGivingADefaultValue() {
    $returnValue = $this->_ioc->getParameter('key_that_has_not_been_set', array(), 'default_value');
    $this->assertEquals('default_value', $returnValue);
  }
}