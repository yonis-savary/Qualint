<?php

use YonisSavary\Qualint\Qualint;

require_once "../vendor/autoload.php";

$qualint = new Qualint(["./badBadFile.php"], [fn($line) => print($line)], Qualint::BEHAVE_COMMIT_TMP);

$qualint->launch();
