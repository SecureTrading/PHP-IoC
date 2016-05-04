<?php

namespace Securetrading\Ioc;

class HelperException extends \Securetrading\Exception {
  const CODE_IOC_INSTANCE_NOT_SET = 1;
  const CODE_PACKAGE_FILE_NOT_ARRAY = 2;
  const CODE_PACKAGE_FILE_BAD_DEFINITIONS = 3;
  const CODE_PACKAGE_FILE_BAD_DEPENDENCIES = 4;
  const CODE_PACKAGE_FILE_BAD_ONLOAD = 5;
  const CODE_PACKAGE_DEFINITION_NOT_FOUND = 6;
}