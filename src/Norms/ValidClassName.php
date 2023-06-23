<?php

namespace YonisSavary\Qualint\Norms;

use YonisSavary\Barn\Analysis\AnalysisText;
use YonisSavary\Barn\Analysis\Analyser;
use YonisSavary\Qualint\AbstractNorm;

class ValidClassName extends AbstractNorm
{
    public function checkFile(Analyser $analyser)
    {
        foreach ($analyser->getClasses() as $class)
        {
            $name = $class->getShortName();
            if (preg_match('/^[A-Z]\w+$/', $name))
                return;

            $newName = ucfirst($name);
            while (strpos($newName, '_') > 1)
                $newName = preg_replace_callback('/([^_])_+(.)/', fn($m) => $m[1] . strtoupper($m[2]), $newName);

            $this->parent->mutate(
                "Bad class name at [$name] (replaced by $newName)",
                function(AnalysisText $text) use ($class, $newName)
                {
                    $startLine = $class->getStartLine();
                    $className = $class->getShortName();

                    $original = $text->captureLines($startLine, $startLine);
                    $transformed = str_replace($className, $newName, $original);
                    $text->replaceLines($startLine, $startLine, $transformed);
                }
            );
        }
    }
}