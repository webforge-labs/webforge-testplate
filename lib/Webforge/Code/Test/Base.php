<?php

namespace Webforge\Code\Test;

use Psc\Code\Code;
use Webforge\Common\System\Dir;
use Webforge\Common\System\File;
use Webforge\Code\Generator\GClass;
use Webforge\Common\ArrayUtil as A;
use Webforge\Common\Util;
use Webforge\Common\System\Util as SystemUtil;
use Webforge\Common\ClassUtil;
use Webforge\Common\DeprecatedException;
use Psc\System\Console\Process;
use Webforge\Process\ProcessBuilder;
use Webforge\Translation\TranslationsBuilder;
use Webforge\Common\JS\JSONConverter;

/**
 * Changes to the PHPUnit-API:
 *
 * - allow just a className (without namespace) for getMock and getMockForAbstractClass. It uses the current Test-Namespace
 * - allow Webforge\Common\System\File for file-related
 * - add assertArrayEquals() as a short coming for equals() with $canonicalize = true
 */
abstract class Base extends Assertions {

  /**
   * Attribute for HTMLTesting Interface
   */
  protected $html;

  /**
   * @var Webforge\Code\Test\FrameworkHelper
   */
  protected $frameworkHelper;

  /**
   * @var Webforge\Common\System\Dir
   */
  protected $testFilesDirectory;

  public function setUp() {
    $this->frameworkHelper = new FrameworkHelper;
    parent::setUp();
  }
    
  /**
   * Asserts that actualCode is equivalent to expectedCode (as PHP Code)
   *
   * Code is considered equal, when it is equal without comments and normalized whitespace
   *
   * @param string $expectedCode complete PHP Code
   * @param string $actualCode complete PHP Code
   */
  public static function assertCodeEquals($actualCode, $expectedCode, $message = '') {
    self::assertThat($actualCode, self::codeEqualTo($expectedCode), $message);
  }
  
  public static function codeEqualTo($code) {
    return new CodeEqualsConstraint($code);
  }

  public function assertGCollectionEquals(Array $objectKeys, $collection) {
    $this->assertArrayEquals(
      $objectKeys,
      $this->reduceCollection($collection, 'key')
    );
  }
  
  /**
   * @return Webforge\Code\Test\GClassTester
   */
  protected function assertThatGClass(GClass $gClass) {
    return new GClassTester($gClass, $this);
  }
  
  /**
   * @return Webforge\Common\System\Dir
   */
  public function getTestDirectory($sub = '/') {
    if (!isset($this->testFilesDirectory)) {
      $this->testFilesDirectory = $GLOBALS['env']['root']->sub('tests/files/');
      $this->testFilesDirectory->resolvePath(); // make abs
    }
    
    return $this->testFilesDirectory->sub($sub);
  }
  
  /* PHPUnit extensions */
  
  public static function assertFileExists($filename, $message = '') {
    if ($filename instanceof \Webforge\Common\System\File) {
      $filename = (string) $filename;
    }
    return parent::assertFileExists($filename, $message);
  }

  public static function assertDirectoryExists($dir, $message = '') {
    return self::assertTrue(is_dir((string) $dir), 'failed asserting that '.$dir.' is a directory. '.$message);
  }

  public function getMock($originalClassName, $methods = array(), array $arguments = array(), $mockClassName = '', $callOriginalConstructor = TRUE, $callOriginalClone = TRUE, $callAutoload = TRUE, $cloneArguments = TRUE, $callOriginalMethods = FALSE, $proxyTarget = null) {
    if (!class_exists($originalClassName)) {
      $originalClassName = ClassUtil::expandNamespace($originalClassName, ClassUtil::getNamespace(get_class($this)));
    }

    $builder = $this->getMockBuilder($originalClassName);

    if (!empty($methods)) {
        $builder->setMethods($methods);
    }

    if (!empty($mockClassName)) {
        throw new \RuntimeException('this is not implemented, yet');
    }

    if ($proxyTarget !== null) {
        throw new \RuntimeException('this is not implemented, yet');
    }

    if (!$callOriginalConstructor) {
        $builder->disableOriginalConstructor();
    }

    if (!empty($arguments) && $callOriginalConstructor) {
        $builder->setConstructorArgs($arguments);
    }

    if (!$callOriginalClone) {
        $builder->disableOriginalClone();
    }

    if (!$cloneArguments) {
        $builder->disableArgumentCloning();
    }

    return $builder->getMock();
  }

  public function getMockForAbstractClass($originalClassName, array $arguments = array(), $mockClassName = '', $callOriginalConstructor = TRUE, $callOriginalClone = TRUE, $callAutoload = TRUE, $mockedMethods = array(), $cloneArguments = TRUE) {
    if (!class_exists($originalClassName)) {
      $originalClassName = ClassUtil::expandNamespace($originalClassName, ClassUtil::getNamespace(get_class($this)));
    }

    $builder = $this->getMockBuilder($originalClassName);

    if (!empty($methods)) {
      $builder->setMethods($methods);
    }

    if (!empty($mockClassName)) {
      throw new \RuntimeException('this is not implemented, yet');
    }

    if (!$callOriginalConstructor) {
      $builder->disableOriginalConstructor();
    }

    if (!empty($arguments) && $callOriginalConstructor) {
      $builder->setConstructorArgs($arguments);
    }

    if (!$callOriginalClone) {
      $builder->disableOriginalClone();
    }

    if (!$cloneArguments) {
      $builder->disableArgumentCloning();
    }

    return $builder->getMockForAbstractClass();
  }
  
  /**
   * Returns only the with $getter retrieved fields from an array of objects
   *
   * @return array
   */
  public function pluck($collection, $getter) {
    return A::pluck($collection, $getter);
  }

  /**
   * Returns only the with $getter retrieved fields from an array of objects
   *
   * @return array
   */
  public function reduceCollection($collection, $getter = 'identifier') {
    return $this->pluck(Util::castArray($collection), $getter);
  }

  // FILE UTILS

  /**
   * @return Webforge\Common\System\Dir
   */
  public function getTempDirectory($sub) {
    return $this->getTestDirectory('tmp/')->sub($sub);
  }

  /**
   * Returns an existing File
   * 
   * the file is asserted for existance
   * 
   * $this->getFile('images/1.jpg');
   * 
   * @param string $relativePath relative to %packageDir%/tests/files/
   * @return Webforge\Common\System\File
   */
  public function getFile($relativePath) {
    if (func_num_args() >= 2) {
      throw DeprecatedException::fromMethodParam(__FUNCTION__, 2, 'params 2 and 3 are deprecated. use the full relative path (its always searched in tests/files/)');
    }

    $file = $this->getTestDirectory()->getFile($relativePath);

    $this->assertFileExists($file, sprintf("getFile('%s') from TestCase cannot find file.", $relativePath));
    
    return $file;
  }

  // Framework UTILS
  /**
   * @return the local package
   */
  public function getPackage() {
    // this is only defined if bootstrap container is avaible and webforge is avaible
    return $this->frameworkHelper->getBootContainer()->getPackage();
  }

  /**
   * @return Webforge\Common\System\Dir
   */
  public function getPackageDir($sub) {
    return $GLOBALS['env']['root']->sub($sub);
  }


  // SYSTEM UTILS

  /**
   * @return Symfony\Component\Process\Process
   */
  public function runPHPFile(File $phpFile) {
    $phpBin = SystemUtil::findPHPBinary();
    
    $process = ProcessBuilder::create()->add($phpBin)->add('-f')->add($phpFile)->getProcess();
    $process->run();
    
    $this->assertTrue($process->isSuccessful(),
                      sprintf("process for phpfile '%s' did not return 0.\ncmd:\n%s\nerr:\n%s\nout:\n%s\n",
                        $phpFile,
                        $process->getCommandLine(),
                        $process->getErrorOutput(),
                        $process->getOutput()
                      )
                     );
    
    return $process;
  }

  // TRANSLATION UTILS

  /**
   * @return Webforge\Translation\TranslationsBuilder
   */
  public function buildTranslations($domain = NULL) {
    return TranslationsBuilder::create($domain);
  }

  public function assertNotTranslationKey($actual, $msg = '') {
    $this->assertNotRegExp('/^[-a-zA-Z0-9_]+(\.[-a-zA-Z0-9_])*$/', $actual, $actual.' looks like an translation key.'.($msg ? "\n".$msg : ''));
  }

  // CSS Utils

  public function css($selector, $html = NULL) {
    $css = new CSSTester($this, $selector, $html);
    $css->asContext();
    return $css;
  }

  // HTMLTesting Interface
  public function setHTML($html) {
    $this->html = $html;
    return $this;
  }

  public function setDebugContextHTML(CSSTester $css, $html, $selectorInfo) {
    $this->html = $html;
    return $this;
  }

  public function getHTML() {
    return $this->html;
  }

  /**
   * @return Webforge\Code\Test\GuzzleResponseAsserter
   */
  public function assertGuzzleResponse($response) {
    return GuzzleResponseAsserter::create($response);
  }

  /*
   * @return Webforge\Code\Test\GuzzleResponseAsserter
   */
  public function assertSymfonyResponse($response) {
    return SymfonyResponseAsserter::create($response);
  }

  /**
   * @return Webforge\Code\Test\GuzzleTester
   */
  public function createGuzzleTester($baseUrl) {
    return new GuzzleTester($baseUrl);
  }


  /**
   * @return mixed
   */
  public function parseJSON($string) {
    $jsonc = new JSONConverter();
    return $jsonc->parse($string);
  }
}
