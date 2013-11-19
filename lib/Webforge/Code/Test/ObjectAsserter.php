<?php

namespace Webforge\Code\Test;

class ObjectAsserter {

  protected $test;
  protected $object;

  protected $context;

  protected $path;

  public function __construct($object, \PHPUnit_Framework_TestCase $test, ObjectAsserter $context = NULL, Array $path = NULL) {
    $this->test = $test;

    $this->object = $object;

    if (!$context) {
      $this->context = $this;
      $this->path = array('$root');
      
      $this->test->assertInternalType('object', $this->object, $this->msg('The given root object should be an object'));
    } else {
      $this->path = $path;
      $this->context = $context;
    }
  }

  /**
   * Asserts that the object has a property with $name
   * 
   * (regardless if it is empty or has some value)
   * @param string $name of the property
   * @param mixed $constraint use a phpunit constraint to check against the value of the property. If this is a string equalTo() is assumed
   */
  public function property($name, $constraint = NULL) {
    $this->isObject();
    $propertyPath = $this->addPath('.'.$name);
    $this->test->assertObjectHasAttribute($name, $this->object, $this->msg('property: %s does not exist', implode('', $propertyPath))); // or: property $this->path() does not have property: $name
    
    $asserter = new ObjectAsserter($this->object->$name, $this->test, $this, $propertyPath);

    if (isset($constraint)) {
      $asserter->is($constraint);
    }

    return $asserter;
  }

  /**
   * @param mixed $constraint use a phpunit constraint to check against the value of the property. If this is a string equalTo() is assumed
   */
  public function is($constraint) {
    if (is_string($constraint)) {
      $constraint = $this->test->equalTo($constraint);
    }

    $this->test->assertThat($this->object, $constraint, $this->msg('%s does not match', $this->path()));
    return $this;
  }

  /**
   * Asserts that the current array has an key $index
   * 
   * @param mixed $constraint use a phpunit constraint to check against the value of the property. If this is a string equalTo() is assumed
   */
  public function key($index, $constraint = NULL) {
    $this->isArray();
    $this->test->assertArrayHasKey($index, $this->object, $this->msg('%s does not have key %s', $this->path(), $index));

    $keyPath = $this->addPath('['.$index.']');
    $asserter = new ObjectAsserter($this->object[$index], $this->test, $this, $keyPath);

    if ($constraint) {
      $asserter->is($constraint);
    }

    return $asserter;
  }

  /**
   * Asserts that the current item is an array
   * 
   * the array can be empty
   */
  public function isArray() {
    $this->test->assertInternalType('array', $this->object, $this->msg('%s is not an array', $this->path()));
    return $this;
  }

  /**
   * Asserts that the current item is an object
   * 
   * the object can be empty
   */
  public function isObject() {
    $this->test->assertInternalType('object', $this->object, $this->msg('%s is not an object', $this->path()));
    return $this;
  }

  protected function addPath($item) {
    return array_merge($this->path, array($item));
  }

  /**
   * Returns to the the last used property() or key() call
   */
  public function end() {
    return $this->context;
  }

  protected function path() {
    return implode('', $this->path);
  }

  protected function msg($msg, $arg1 = NULL, $arg2 = NULL) {
    $args = func_get_args();
    $msg = array_shift($args);

    return vsprintf($msg, $args);
  }
}