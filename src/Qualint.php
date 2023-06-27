<?php

namespace YonisSavary\Qualint;

use InvalidArgumentException;
use YonisSavary\Barn\Analysis\Analyser;
use YonisSavary\Qualint\AbstractNorm;
use YonisSavary\Qualint\Norms\ValidClassName;
use YonisSavary\Qualint\Norms\ValidFunctionName;
use YonisSavary\Qualint\Norms\ValidNamespace;
use YonisSavary\Qualint\Norms\ValidQuotes;
use YonisSavary\Qualint\Norms\ValidUses;
use YonisSavary\Qualint\Norms\ValidWhitespaces;

class Qualint
{
    const BEHAVE_PREVIEW   = 0;
    const BEHAVE_OVERWRITE = 1;
    const BEHAVE_BACKUP    = 2;
    const BEHAVE_CLONE     = 3;

    protected array $files = [];

    /** @var array<string,Analyser> */
    protected array $pool = [];

    /** @var array<callable> */
    protected array $loggers = [];

    /** @var array<AbstractNorm> */
    protected array $norms = [];

    protected Analyser $activeAnalyser;
    protected int $behavior;

    protected int $textPad = 0;

    public function __construct(array $files, array $loggers=[], int $behavior=self::BEHAVE_PREVIEW)
    {
        $this->files = $files;
        $this->loggers = $loggers;
        $this->behavior = $behavior;

        foreach ([
            ValidFunctionName::class,
            ValidClassName::class,
            ValidNamespace::class,
            ValidUses::class,
            ValidQuotes::class,
            ValidWhitespaces::class,
        ] as $class)
            $this->addNorm($class);

        $this->pool = [];
        $this->textPad = 0;
        foreach ($files as $file)
        {
            $this->textPad = max($this->textPad, strlen($file));
            $this->pool[md5($file)] = Analyser::fromFile($file);
        }
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
            $this->log('Checking norm ['.$norm::class.']');
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
            case self::BEHAVE_CLONE:
                $this->commitToClone();
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
        $lineStr = $line ? ":$line" : '';

        $tp = $this->textPad;
        $path = $analyser->getPath();
        $this->log(
            sprintf(
                ' - [%s%s]%s %s',
                $path,
                $lineStr,
                str_repeat(' ', max(0, $tp+5 -(strlen($path) + strlen($lineStr)))),
                $message
            ));
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

            $this->log("Overwriting file at [$baseFile]");

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

            $tp = $this->textPad;
            $this->log(sprintf(' - %s%s : %s (%sko) => %s (%sko)',
                $baseFile,
                str_repeat(' ', $tp-strlen($baseFile)),
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

            $backupFile = uniqid($baseFile) . '.php';
            $this->log("Writing backup file at [$backupFile]");

            file_put_contents($backupFile, file_get_contents($baseFile));
            file_put_contents($baseFile, $newContent);
        }
    }

    public function commitToClone()
    {
        $this->log("\nSaving changes in clone files :");
        foreach ($this->pool as $file)
        {
            if (!$file->gotChanges())
                continue;

            $baseFile = $file->getPath();
            $newContent = (string) $file->getAnalysisText();

            $cloneFile = uniqid($baseFile) . '.php';
            $this->log("Writing clone file at [$cloneFile]");

            file_put_contents($cloneFile, $newContent);
        }
    }
}