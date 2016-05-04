<?php

namespace Securetrading\Ioc;

interface IocInterface {
  public function set($type, $value);
  public function has($type);
  public function get($type, array $params = array());
  public function create($type, array $params = array());
  public function getSingleton($type);
}