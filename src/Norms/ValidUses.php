<?php

namespace YonisSavary\Qualint\Norms;

use YonisSavary\Barn\Analysis\Analyser;
use YonisSavary\Barn\Analysis\AnalysisText;
use YonisSavary\Qualint\AbstractNorm;

class ValidUses extends AbstractNorm
{
    public function checkFile(Analyser $analyser)
    {
        $uses = $analyser->getUses();
        for ($i=0; $i<count($uses); $i++)
        {
            $uses = $analyser->getUses();
            $use = $uses[$i] ?? null;
            if (!$use) break;

            $class = $use->class;
            $classname = preg_replace('/^.+\\\\/', '', $class);
            $line = $use->line;
            $alias = $use->alias;

            $matches = [];
            preg_match_all('/\b'.$classname.'\b/', $analyser->getAnalysisText(), $matches);

            $isUsed = count($matches[0]) > 1;

            if (!$isUsed)
            {
                $this->parent->mutate(
                    "Removing unused use statement for [$classname]",
                    fn(AnalysisText $text) => $text->replaceLines($line, $line, ''),
                    line: $line
                );
                $i--;
                continue;
            }

            if (class_exists($class))
                continue;

            $found = false;
            foreach (get_declared_classes() as $possibleClass)
            {
                if (!str_ends_with($possibleClass, "\\".$classname))
                    continue;

                $replacement = "use $possibleClass";
                if ($alias) $replacement .= " as $alias";
                $replacement .=";\n";

                $this->parent->mutate(
                    "Fixing bad use statement [$class] to [$possibleClass]",
                    fn(AnalysisText $text) => $text->replaceLines($line, $line, $replacement),
                    line: $line
                );

                $found = true;
                break;
            }
            if ($found)
                continue;

            $this->parent->mutate(
                'Removing bad use statement',
                fn(AnalysisText $text) => $text->replaceLines($line, $line, ''),
                line: $line
            );
            $i--;
        }
    }
}