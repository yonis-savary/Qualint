<?php

namespace YonisSavary\Qualint\Norms;

use YonisSavary\Barn\Analysis\AnalysisText;
use YonisSavary\Barn\Analysis\Analyser;
use YonisSavary\Qualint\AbstractNorm;

class ValidNamespace extends AbstractNorm
{
    protected function normalizePath(string &$path)
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/\/{2,}/', '/', $path);
    }

    protected function normalizeNamespace(string &$path)
    {
        $path = str_replace('/', '\\', $path);
        $path = preg_replace('/\\\\{2,}/', '\\', $path);
    }

    public function checkFile(Analyser $analyser)
    {
        if (!($actualNamespace = $analyser->getNamespace()))
            return;
        $actualNamespaceString = $actualNamespace->namespace;

        $cwd = getcwd();
        $this->normalizePath($cwd);

        $expectedNamespace = $analyser->getPath();

        $basename = basename($expectedNamespace);
        $basenamePosition = strpos($expectedNamespace, $basename);

        $expectedNamespace = substr($expectedNamespace, 0, $basenamePosition-1);

        $this->normalizeNamespace($expectedNamespace);
        $expectedNamespace = str_replace($cwd, '', $expectedNamespace);

        if (str_starts_with($expectedNamespace, '/'))
            $expectedNamespace = substr($expectedNamespace, 1);

        if ($expectedNamespace === $actualNamespaceString)
            return;

        $this->parent->mutate(
            "Expected namespace [$expectedNamespace] don't meet [$actualNamespaceString]",
            function(AnalysisText &$file) use ($actualNamespace, $expectedNamespace)
            {
                $line = $actualNamespace->line;
                $file->replaceLines($line, $line, $expectedNamespace ? "namespace $expectedNamespace;": '');
            },
            line: $actualNamespace->line
        );
    }
}