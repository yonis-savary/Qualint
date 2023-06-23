<?php

namespace YonisSavary\Qualint;

use InvalidArgumentException;
use YonisSavary\Barn\Analysis\Analyser;
use YonisSavary\Qualint\AbstractNorm;
use YonisSavary\Qualint\Norms\ValidClassName;
use YonisSavary\Qualint\Norms\ValidFunctionName;
use YonisSavary\Qualint\Norms\ValidNamespace;
use YonisSavary\Qualint\Norms\ValidWhitespaces;

class Qualint
{
    const BEHAVE_PREVEW     = 0;
    const BEHAVE_COMMIT     = 1;
    const BEHAVE_COMMIT_TMP = 2;

    protected array $files = [];

    /** @var array<string,Analyser> */
    protected array $pool = [];

    /** @var array<callable> */
    protected array $loggers = [];

    /** @var array<AbstractNorm> */
    protected array $norms = [];

    protected array $warnings = [];

    protected Analyser $activeAnalyser;
    protected int $behavior;

    public function __construct(array $files, array $loggers=[], int $behavior=self::BEHAVE_PREVEW)
    {
        $this->files = $files;
        $this->loggers = $loggers;
        $this->behavior = $behavior;

        foreach ([
            ValidFunctionName::class,
            ValidClassName::class,
            ValidNamespace::class,
            ValidWhitespaces::class,
        ] as $class)
            $this->addNorm($class);

        $this->pool = [];
        foreach ($files as $file)
            $this->pool[md5($file)] = Analyser::fromFile($file);
    }

    public function addNorm(string $class)
    {
        if (!class_exists($class))
            throw new InvalidArgumentException("[$class] class does not exists !");

        if (!($parent = class_parents($class)))
            throw new InvalidArgumentException("[$class] must extends from [".AbstractNorm::class.']');

        if (!in_array(AbstractNorm::class, $parent))
            throw new InvalidArgumentException("[$class] must extends from [".AbstractNorm::class.']');

        $this->norms[] = new $class($this);
    }

    public function addLogger(callable $function)
    {
        $this->loggers[] = $function;
    }


    public function log(string ...$lines)
    {
        foreach ($this->loggers as $logger)
        {
            foreach ($lines as $line)
                $logger($line);
        }
    }

    public function launch()
    {
        foreach ($this->norms as $norm)
        {
            $this->log('Checking norm ['.$norm::class."]\n");
            foreach ($this->pool as $_ => &$analyser)
            {
                $this->activeAnalyser = &$analyser;
                $norm->checkFile($analyser);
            }
        }

        switch ($this->behavior)
        {
            case self::BEHAVE_COMMIT:
                $this->commitDirect();
                break;
                case self::BEHAVE_PREVEW:
                $this->preview();
                break;
            case self::BEHAVE_COMMIT_TMP:
                $this->commitToTemp();
                break;
        }
    }

    public function mutate(
        string $message,
        callable $mutator
    ) {
        $analyser = $this->activeAnalyser;
        $text = $analyser->getAnalysisText();
        $this->log("  $message\n");
        $mutator($text);
        $analyser->update($text);
    }

    public function getActiveAnalyser()
    {
        return $this->activeAnalyser;
    }


    public function logWarnings()
    {
        foreach ($this->warnings as $md5 => $changes)
        {
            echo "$md5 :\n";
            foreach ($changes as $change)
                $this->log(" - ".$change["message"]);
        }
    }

    public function getWarnings(): array
    {
        return array_map(fn($e) => $e["message"], $this->warnings);
    }

    public function commitDirect()
    {

    }
    public function preview()
    {

    }

    public function commitToTemp()
    {
        $outdir = "./temp-qualint-output";
        if (!is_dir($outdir))
            mkdir($outdir);

        foreach ($this->pool as $file)
        {
            $out = tempnam($outdir, "output");
            file_put_contents($out, $file->getAnalysisText());
        }
    }

}