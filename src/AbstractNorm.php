<?php

namespace YonisSavary\Qualint;

use YonisSavary\Barn\Analysis\Analyser;
use YonisSavary\Barn\Analysis\AnalyserPool;
use YonisSavary\Qualint\Qualint;

abstract class AbstractNorm
{
    public function __construct(
        public Qualint $parent
    ){ }

    public function log(string ...$lines)
    {
        $this->parent->log(...$lines);
    }

    public function checkFile(Analyser $analyser)
    {

    }
}