<?php

namespace Securetrading\Ioc;

class IocException extends \Securetrading\Exception {
  const CODE_INVALID_ALIAS = 1;
  const CODE_INVALID_TYPE = 2;
  const CODE_INVALID_CLASS = 3;
  const CODE_CIRCULAR_RESOLUTION = 4;
  const CODE_MISSING_ALIAS = 5;
  const CODE_OPTION_MISSING = 6;
  const CODE_PARAM_MISSING = 7;
  const CODE_TYPE_NOT_INSTANTIABLE = 8;
}