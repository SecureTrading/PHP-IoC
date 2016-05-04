<?php

class TwoParamConstructor {
  public function __construct($param1, $param2) {
    $this->_param1 = $param1;
    $this->_param2 = $param2;
  }

  public function getParam1() {
    return $this->_param1;
  }

  public function getParam2() {
    return $this->_param2;
  }
}