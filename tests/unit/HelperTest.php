<?php

namespace Securetrading\Ioc\Tests\Unit;

use org\bovigo\vfs\vfsStream;

class HelperTest extends \Securetrading\Unittest\UnittestAbstract {
  protected $_helper;

  protected $_rootDir;

  protected function _createFileInPackageEtcDir($filename, $contents) {
    if (!file_exists($this->_rootDir->url() . "/package_folder/etc/")) {
      $newDirectory = vfsStream::newDirectory("package_folder/etc", 0777);
      $newDirectory->at($this->_rootDir);
    }
    $newFile = vfsStream::newFile($filename, 0777);
    $newFile->at($this->_rootDir->getChild("package_folder")->getChild("etc"));
    $newFile->setContent($contents);
    return $newFile;
  }

  protected function _getPackageFileContents($packageDefinitions) { // Note - did not typehint to array because sometimes we want to build and test invalid files.
    $packageDefinitionsArray = var_export($packageDefinitions, true);
    $contents = <<<PACKAGE_DEFINITION_CONTENTS
      <?php
      return $packageDefinitionsArray;
PACKAGE_DEFINITION_CONTENTS;
    return $contents;
  }

  protected function _getIocMock() {
    return $this->getMock('\Securetrading\Ioc\IocInterface');
  }

  public function setUp() : void {
    $this->_helper = new \Securetrading\Ioc\Helper();
    $this->_rootDir = vfsStream::setup('rootTestDirectory');
  }
  
  /**
   * 
   */
  public function testInstance() {
    $returnValue = \Securetrading\Ioc\Helper::instance();
    $this->assertInstanceOf(get_class($this->_helper), $returnValue);
  }

  /**
   *
   */
  public function testInstance_CreatesIocInstance() {
    $iocHelper = \Securetrading\Ioc\Helper::instance();
    $this->assertInstanceOf('\Securetrading\Ioc\Ioc', $iocHelper->getIoc());
  }

  /**
   *
   */
  public function testInstance_WhenGivenIocInstance() {
    $iocStub = $this->getMock('\Securetrading\Ioc\IocInterface');
    $iocHelper = \Securetrading\Ioc\Helper::instance($iocStub);
    $this->assertSame($iocStub, $iocHelper->getIoc());
  }

  /**
   * 
   */
  public function testSetIoc_HasCorrectReturnValue() {
    $iocMock = $this->_getIocMock();
    $returnValue = $this->_helper->setIoc($iocMock);
    $this->assertSame($this->_helper, $returnValue);
  }

  /**
   * @expectedException \Securetrading\Ioc\HelperException
   * @expectedExceptionCode \Securetrading\Ioc\HelperException::CODE_IOC_INSTANCE_NOT_SET
   */
  public function testGetIoc_WhenNotSet() {
    $this->_helper->getIoc();
  }

  /**
   *
   */
  public function testGetIoc_WhenSet() {
    $iocMock = $this->getMock('\Securetrading\Ioc\IocInterface');
    $this->_helper->setIoc($iocMock);
    $returnValue = $this->_helper->getIoc();
    $this->assertSame($iocMock, $returnValue);
  }

  /**
   * 
   */
  public function testAddVendorDirs() {
    $this->_helper->addVendorDirs("dir1");
    $this->assertEquals(array('dir1' => true), $this->_getPrivateProperty($this->_helper, '_vendorDirs'));

    $this->_helper->addVendorDirs(array("dir2", "dir3"));
    $this->assertEquals(array('dir1' => true, 'dir2' => true, 'dir3' => true), $this->_getPrivateProperty($this->_helper, '_vendorDirs'));

    $returnValue = $this->_helper->addVendorDirs("dir5");
    $this->assertSame($this->_helper, $returnValue);
  }

  /**
   * 
   */
  public function testAddEtcDirs() {
    $this->_helper->addEtcDirs("dir1");
    $this->assertEquals(array('dir1' => true), $this->_getPrivateProperty($this->_helper, '_etcDirs'));

    $this->_helper->addEtcDirs(array("dir2", "dir3"));
    $this->assertEquals(array('dir1' => true, 'dir2' => true, 'dir3' => true), $this->_getPrivateProperty($this->_helper, '_etcDirs'));

    $returnValue = $this->_helper->addEtcDirs("dir5");
    $this->assertSame($this->_helper, $returnValue);
  }

  /**
   * 
   */
  public function testAddDefinitionFiles() {
    $this->_helper->addDefinitionFiles("file1");
    $this->assertEquals(array('file1' => true), $this->_getPrivateProperty($this->_helper, '_definitionFiles'));

    $this->_helper->addDefinitionFiles(array("file2", "file3"));
    $this->assertEquals(array('file1' => true, 'file2' => true, 'file3' => true), $this->_getPrivateProperty($this->_helper, '_definitionFiles'));

    $returnValue = $this->_helper->addDefinitionFiles("file5");
    $this->assertSame($this->_helper, $returnValue);
  }

  /**
   *
   */
  public function testLoadPackages_ReturnValue() {
    $returnValue = $this->_helper->loadPackages(array(), '');
    $this->assertSame($this->_helper, $returnValue);
  }

  /**
   *
   */
  public function testLoadPackages() {
    $contents1 = $this->_getPackageFileContents(array(
      'package_definition_1' => array(
	'definitions' => array(
	  'a' => 'b',
	  'c' => 'd',
        ),
        'dependencies' => array(),
      ),
    ));
    $contents2 = $this->_getPackageFileContents(array(
      'package_definition_2' => array(
	'definitions' => array(
          'e' => 'f',
        ),
        'dependencies' => array(),
      ),
    ));

    $rootDirectory = vfsStream::setup('rootTestDirectory', 0777, array(
      'outer' => array(
        'package_folder_1' => array(
          'etc' => array(
            '0_package_definition_1_ioc.php' => $contents1,
            '1_package_definition_2_ioc.php' => $contents2,
          ),
        ),
      ),
    ));

    $iocMock = $this->getMock('\Securetrading\Ioc\IocInterface');
    $iocMock
      ->expects($this->exactly(3))
      ->method('set')
      ->withConsecutive(
        array($this->equalTo('a'), $this->equalTo('b')),
	array($this->equalTo('c'), $this->equalTo('d')),
	array($this->equalTo('e'), $this->equalTo('f'))
      )
    ;

    $this->_helper->setIoc($iocMock);

    $this->_helper->addVendorDirs($rootDirectory->getChild('outer')->url());
    $returnValue = $this->_helper->loadPackages(array('package_definition_1', 'package_definition_2'));

    $this->assertEquals(array('package_definition_1', 'package_definition_2'), $this->_helper->getLoadedPackageNames());
    $this->assertSame($this->_helper, $returnValue);
  }

  /**
   *
   */
  public function testLoadPackage_LoadsAPackageCorrectly() {
    $contents1 = $this->_getPackageFileContents(array(
      'package_definition_1' => array(
	'definitions' => array(
	  'a' => 'b',
	  'c' => 'd',
        ),
        'dependencies' => array(),
      ),
    ));
    $contents2 = $this->_getPackageFileContents(array(
      'package_definition_2' => array(
	'definitions' => array(
          'e' => 'f',
        ),
        'dependencies' => array(),
      ),
    ));

    $rootDirectory = vfsStream::setup('rootTestDirectory', 0777, array(
      'outer' => array(
        'package_folder_1' => array(
          'etc' => array(
            '0_package_definition_1_ioc.php' => $contents1,
            '1_package_definition_2_ioc.php' => $contents2,
          ),
        ),
      ),
    ));

    $packageName = 'package_definition_1';

    $iocMock = $this->getMock('\Securetrading\Ioc\Ioc');
    $iocMock
      ->expects($this->any())
      ->method('set')
      ->withConsecutive(
        array($this->equalTo('a'), $this->equalTo('b')),
	array($this->equalTo('c'), $this->equalTo('d')),
	array($this->equalTo('e'), $this->equalTo('f'))
      )
    ;
    $this->_helper->setIoc($iocMock);

    $this->_helper->addVendorDirs($rootDirectory->getChild('outer')->url());
    $returnValue = $this->_helper->loadPackages(array('package_definition_1'));

    $this->assertEquals(array('package_definition_1'), $this->_helper->getLoadedPackageNames());
  }

  
  /**
   * @covers \Securetrading\Ioc\HelperTest::getPackageDefinitionFiles
   */
  public function test_findAndSortPackageDefinitions() {
    $contents1 = $this->_getPackageFileContents(array(
      'package_name_1' => array(
        'definitions' => array(
          'exampleKey' => 'exampleValue',
        ),
	'dependencies' => array(
				
        ),
      ),
    ));

    $contents2 = $this->_getPackageFileContents(array(
      'package_name_2' => array(
        'definitions' => array(
          'exampleKey' => 'exampleValue',
        ),
	'dependencies' => array(
				
        ),
      ),
    ));
    
    $contents3 = $this->_getPackageFileContents(array(
      'package_name_3' => array(),
    ));

    $contents4 = $this->_getPackageFileContents(array(
      'package_name_4' => array(),
    ));

    $rootDirectory = vfsStream::setup('rootTestDirectory', 0777, array(
      'definition_file_that_will_be_loaded_explicitly' => $contents4,
      'my_etc_dir' => array(
        '3_packagename_ioc.php' => $contents3,
      ),
      'outer_vendor_dir' => array(
        'package_folder_1' => array(
          'etc' => 'This is a file but etc should be a folder',
        ),
        'package_folder_2' => array(
          'etc2' => array(
	    '0_packagename_ioc.php' => 'This file is named correctly but it is not in a [package_name]/etc/ folder.',
	  ),
        ),
        'package_folder_3' => array(
          'etc' => array(
	    'file1' => 'This file is not named correctly.',
	    '1_packagename_ioc.php' => $contents1,
	    '2_packagename_ioc.php' => $contents2,
	    '3_packagename_ioc.rb' => 'This file is not named correctly: it should have a .php extension.',
	    '4_packagename_ioc.php' => 'This file is also named correctly but below it will be made unreadable',
	    '5_packagename_ioc.php' => array(
	      'filename' => 'the_definition_set_was_named_correctly_but_it_was_actually_a_directory',
	    ),
	  ),
          'etc2' => array(
	    '6_packagename_ioc.php' => 'This file is named correctly but it is not in an etc dir.',
	  ),
	  'file_in_root_dir' => 'This file is named incorrectly and in the wrong location.',
        ),
        '.' => array(
          'etc' => array(
	    '6_packagename_ioc.php' => 'This file is named correctly but the . directory should be ignored.',
	  ),
        ),
        '..' => array(
          'etc' => array(
	    '7_packagename_ioc.php' => 'This file is named correctly but the .. directory should be ignored.',
	  ),
        ),
        '8_packagename_ioc.php' => 'This file is named correctly but is not in the correct location.',
      ),
      'etc' => array(
        '9_packagename_ioc.php' => 'This file is named correctly but is accessed through a .. dir.',
      ),
    ));

    $rootDirectory->getChild('outer_vendor_dir')->getChild('package_folder_3')->getChild('etc')->getChild('4_packagename_ioc.php')->chmod(0000);

    $expectedLoadedDefinitionSetFiles = array(
      'vfs://rootTestDirectory/outer_vendor_dir/package_folder_3/etc/1_packagename_ioc.php',
      'vfs://rootTestDirectory/outer_vendor_dir/package_folder_3/etc/2_packagename_ioc.php',
      'vfs://rootTestDirectory/my_etc_dir/3_packagename_ioc.php',
      'vfs://rootTestDirectory/definition_file_that_will_be_loaded_explicitly',
    );

    $this->assertEquals(array(), $this->_helper->getPackageDefinitionFiles());

    $this->_helper->addVendorDirs($rootDirectory->getChild('outer_vendor_dir')->url());
    $this->_helper->addEtcDirs($rootDirectory->getChild('my_etc_dir')->url());
    $this->_helper->addDefinitionFiles($rootDirectory->getChild('definition_file_that_will_be_loaded_explicitly')->url());
    $this->_($this->_helper, '_findAndSortPackageDefinitions');
    
    $this->assertEquals($expectedLoadedDefinitionSetFiles, $this->_helper->getPackageDefinitionFiles());
  }

  /**
   * @covers \Securetrading\Ioc\HelperTest::getPackageDefinitions
   */
  public function test_readPackageDefinitions() {
    $contents1 = $this->_getPackageFileContents(array(
      'package_name_1' => array(
        'definitions' => array(
          'exampleKey' => 'exampleValue',
        ),
	'dependencies' => array(
				
        ),
      ),
      'package_name_2' => array(
        'definitions' => array(
          'exampleKey' => 'exampleValue',
        ),
	'dependencies' => array(
          'package_name_1',
        ),
      ),
    ));

    $contents2 = $this->_getPackageFileContents(array(
      'package_name_1' => array(
        'definitions' => array(
          'redefinedDefinitions_ExampleKey' => 'redefinedDefinitions_ExampleValue',
        ),
	'dependencies' => array(
          'redefinedDependency',
        ),
      ),
      'package_name_3' => array(
        'definitions' => array(
          'exampleKey' => 'exampleValue',
        ),
	'dependencies' => array(
          'package_name_1',
        ),
      ),
    ));

    $rootDirectory = vfsStream::setup('rootTestDirectory', 0777, array(
      'outer' => array(
        'file1' => $contents1,
	'file2' => $contents2,
      )
    ));

    $inputFiles = array(
      $rootDirectory->getChild('outer')->getChild('file1')->url(),
      $rootDirectory->getChild('outer')->getChild('file2')->url(),
    );

    $expectedPackageDefinitions = array(
      'package_name_1' => array(
        'definitions' => array(
          'redefinedDefinitions_ExampleKey' => 'redefinedDefinitions_ExampleValue',
        ),
	'dependencies' => array(
          'redefinedDependency',
        ),
      ),
      'package_name_2' => array(
        'definitions' => array(
          'exampleKey' => 'exampleValue',
        ),
	'dependencies' => array(
          'package_name_1',				
        ),
      ),
      'package_name_3' => array(
        'definitions' => array(
          'exampleKey' => 'exampleValue',
        ),
	'dependencies' => array(
          'package_name_1',
        ),
      ),
    );

    $this->assertEquals(array(), $this->_helper->getPackageDefinitions());
    $this->_($this->_helper, '_readPackageDefinitions', $inputFiles);
    $this->assertEquals($expectedPackageDefinitions, $this->_helper->getPackageDefinitions());
  }

  /**
   * @expectedException \Securetrading\Ioc\HelperException
   * @expectedExceptionCode \Securetrading\Ioc\HelperException::CODE_PACKAGE_FILE_NOT_ARRAY
   */
  public function test_readPackageDefinitions_FileDoesNotReturnAnArray() {
    $contents1 = $this->_getPackageFileContents('string - this should be an array');

    $rootDirectory = vfsStream::setup('rootTestDirectory', 0777, array(
      'outer' => array(
        'file1' => $contents1,
      )
    ));

    $inputFiles = array(
      $rootDirectory->getChild('outer')->getChild('file1')->url(),
    );

    $this->_($this->_helper, '_readPackageDefinitions', $inputFiles);
  }

  /**
   * @expectedException \Securetrading\Ioc\HelperException
   * @expectedExceptionCode \Securetrading\Ioc\HelperException::CODE_PACKAGE_FILE_BAD_DEFINITIONS
   */
  public function test_readPackageDefinitions_DefinitionsIsNotAnArray() {
    $contents1 = $this->_getPackageFileContents(array(
      'package_definition_1' => array(
        'definitions' => 'this should be an array',
	'dependencies' => array(),
      ),
    ));

    $rootDirectory = vfsStream::setup('rootTestDirectory', 0777, array(
      'outer' => array(
        'file1' => $contents1,
      )
    ));

    $inputFiles = array(
      $rootDirectory->getChild('outer')->getChild('file1')->url(),
    );

    $this->_($this->_helper, '_readPackageDefinitions', $inputFiles);
  }

  /**
   * @expectedException \Securetrading\Ioc\HelperException
   * @expectedExceptionCode \Securetrading\Ioc\HelperException::CODE_PACKAGE_FILE_BAD_DEPENDENCIES
   */
  public function test_readPackageDefinitions_DependenciesIsNotAnArray() {
    $contents1 = $this->_getPackageFileContents(array(
      'package_definition_1' => array(
	'definitions' => array(),
        'dependencies' => 'this should be an array',
      ),
    ));

    $rootDirectory = vfsStream::setup('rootTestDirectory', 0777, array(
      'outer' => array(
        'file1' => $contents1,
      )
    ));

    $inputFiles = array(
      $rootDirectory->getChild('outer')->getChild('file1')->url(),
    );

    $this->_($this->_helper, '_readPackageDefinitions', $inputFiles);
  }

  /**
   * @expectedException \Securetrading\Ioc\HelperException
   * @expectedExceptionCode \Securetrading\Ioc\HelperException::CODE_PACKAGE_FILE_BAD_ONLOAD
   */
  public function test_readPackageDefinitions_OnLoadIsNotCallable() {
    $contents1 = $this->_getPackageFileContents(array(
      'package_definition_1' => array(
        'onload' => 'should be a callable function',
      ),
    ));

    $rootDirectory = vfsStream::setup('rootTestDirectory', 0777, array(
      'outer' => array(
        'file1' => $contents1,
      )
    ));

    $inputFiles = array(
      $rootDirectory->getChild('outer')->getChild('file1')->url(),
    );

    $this->_($this->_helper, '_readPackageDefinitions', $inputFiles);
  }

  /**
   * @covers \Securetrading\Ioc\Helper::getLoadedPackageNames
   */
  public function test_loadPackage() {
    $contents1 = $this->_getPackageFileContents(array(
      'package_definition_1' => array(
	'definitions' => array(
	  'a' => 'b',
	  'c' => 'd',
        ),
        'dependencies' => array(
          'package_definition_2',
        ),
      ),
    ));
    $contents2 = $this->_getPackageFileContents(array(
      'package_definition_2' => array(
	'definitions' => array(
          'e' => 'f',
        ),
        'dependencies' => array(
          'package_definition_1', // ensure we do not infinitely recurse
        ),
      ),
    ));

    $rootDirectory = vfsStream::setup('rootTestDirectory', 0777, array(
      'outer' => array(
        'package_folder_1' => array(
          'etc' => array(
            '0_package_definition_1_ioc.php' => $contents1,
            '1_package_definition_2_ioc.php' => $contents2,
          ),
        ),
      ),
    ));

    $packageName = 'package_definition_1';

    $iocMock = $this->getMock('\Securetrading\Ioc\Ioc');
    $iocMock
      ->expects($this->exactly(3))
      ->method('set')
      ->withConsecutive(
        array($this->equalTo('a'), $this->equalTo('b')),
	array($this->equalTo('c'), $this->equalTo('d')),
	array($this->equalTo('e'), $this->equalTo('f'))
      )
    ;
    $this->_helper->setIoc($iocMock);

    $this->_helper->addVendorDirs($rootDirectory->getChild('outer')->url());

    $this->_($this->_helper, '_findAndSortPackageDefinitions');
    $this->_($this->_helper, '_readPackageDefinitions', $this->_helper->getPackageDefinitionFiles());

    $this->_($this->_helper, '_loadPackage', $packageName);

    $this->assertEquals(array('package_definition_1', 'package_definition_2'), $this->_helper->getLoadedPackageNames());
  }

  /**
   * @expectedException \Securetrading\Ioc\HelperException
   * @expectedExceptionCode \Securetrading\Ioc\HelperException::CODE_PACKAGE_DEFINITION_NOT_FOUND
   */
  public function test_loadPackage_WhenPackageDefinitionNotFound() {
    $this->_($this->_helper, '_loadPackage', 'missing_package_name');
  }
}