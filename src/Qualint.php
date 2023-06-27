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
    const BEHAVE_PREVIEW   = 0;
    const BEHAVE_OVERWRITE = 1;
    const BEHAVE_BACKUP    = 2;

    protected array $files = [];

    /** @var array<string,Analyser> */
    protected array $pool = [];

    /** @var array<callable> */
    protected array $loggers = [];

    /** @var array<AbstractNorm> */
    protected array $norms = [];

    protected Analyser $activeAnalyser;
    protected int $behavior;

    public function __construct(array $files, array $loggers=[], int $behavior=self::BEHAVE_PREVIEW)
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
            $this->log('Checking norm ['.$norm::class."]");
            foreach ($this->pool as $_ => &$analyser)
            {
                $this->activeAnalyser = &$analyser;
                $norm->checkFile($analyser);
            }
        }

        switch ($this->behavior)
        {
            case self::BEHAVE_OVERWRITE:
                $this->commitDirect();
                break;
                case self::BEHAVE_PREVIEW:
                $this->preview();
                break;
            case self::BEHAVE_BACKUP:
                $this->commitToTemp();
                break;
        }
    }

    public function mutate(
        string $message,
        callable $mutator,
        ?int $line=null,
        ?int $offset=null
    ) {
        $analyser = $this->activeAnalyser;
        $text = $analyser->getAnalysisText();

        if ($offset) $line = $text->offsetLine($offset);
        $lineStr = $line ? ":$line" : "";

        $this->log("  ". $analyser->getPath() . $lineStr ." ".$message);
        $mutator($text);
        $analyser->update($text);
    }

    public function getActiveAnalyser()
    {
        return $this->activeAnalyser;
    }

    public function commitDirect()
    {
        foreach ($this->pool as $file)
        {
            if (!$file->gotChanges())
                continue;

            $baseFile = $file->getPath();
            $newContent = (string) $file->getAnalysisText();

            $this->log("Overwriting file at [$baseFile]\n");

            file_put_contents($baseFile, $newContent);
        }
    }

    public function preview()
    {
        $this->log("\nChanges that would be commited :");
        foreach ($this->pool as $file)
        {
            if (!$file->gotChanges())
                continue;

            $baseFile = $file->getPath();
            $newContent = (string) $file->getAnalysisText();

            $diffFile = uniqid($baseFile);

            file_put_contents($diffFile, $newContent);

            $this->log(sprintf("%s : %s (%sko) => %s (%sko)",
                $baseFile,
                md5_file($baseFile),
                round(filesize($baseFile) / 1024, 2),
                md5_file($diffFile),
                round(filesize($diffFile) / 1024, 2)
            ));

            unlink($diffFile);
        }
    }

    public function commitToTemp()
    {
        $this->log("\nSaving changes and putting originals to backup files :");
        foreach ($this->pool as $file)
        {
            if (!$file->gotChanges())
                continue;

            $baseFile = $file->getPath();
            $newContent = (string) $file->getAnalysisText();

            $backupFile = uniqid($baseFile);
            $this->log("Writing backup file at [$backupFile]\n");

            file_put_contents($backupFile, file_get_contents($baseFile));
            file_put_contents($baseFile, $newContent);
        }
    }
}