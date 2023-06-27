<?php

use YonisSavary\FalseLint\Qualint;
use YonisSavary\MyLint\AbstractNorm;

require_once "../vendor/autoload.php";

$qualint = new Qualint(["./badBadFile.php"], [fn($line) => print($line)], Qualint::BEHAVE_BACKUP);

$qualint->launch();
