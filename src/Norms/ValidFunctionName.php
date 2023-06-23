<?php

namespace YonisSavary\Qualint\Norms;

use ReflectionFunction;
use ReflectionMethod;
use YonisSavary\Barn\Analysis\AnalysisText;
use YonisSavary\Barn\Analysis\Analyser;
use YonisSavary\Qualint\AbstractNorm;

class ValidFunctionName extends AbstractNorm
{
    protected function checkFunction(Analyser $analyser, ReflectionFunction|ReflectionMethod $function)
    {
        $name = $function->getName();

        if (preg_match('/^[a-z]\w+$/', $name))
            return;

        $newName = lcfirst($name);
        while (strpos($newName, '_') > 1)
            $newName = preg_replace_callback('/([^_])_+(.)/', fn($m) => $m[1] . strtoupper($m[2]), $newName);

        $this->parent->mutate(
            "Bad method name [$name] (Replaced by $newName)",
            function(AnalysisText $text) use ($function, $newName) {
                $name = $function->getName();
                $line = $function->getStartLine();

                $original = $text->captureLines($line, $line);
                $original = str_replace($name, $newName, $original);
                $text->replaceLines($line, $line, $original);
            }
        );
    }

    public function checkFile(Analyser $analyser)
    {
        foreach ($analyser->getFunctions() as $function)
            $this->checkFunction($analyser, $function);

        foreach ($analyser->getClasses() as $class)
        {
            foreach ($class->getMethods() as $method)
                $this->checkFunction($analyser, $method);
        }
    }
}