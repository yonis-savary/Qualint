<?php

namespace YonisSavary\Qualint\Norms;

use YonisSavary\Barn\Analysis\Analyser;
use YonisSavary\Barn\Analysis\AnalysisText;
use YonisSavary\Qualint\AbstractNorm;

class ValidWhitespaces extends AbstractNorm
{
    public function checkFile(Analyser $analyser)
    {
        $content = (string) $analyser->getProcessedContent();
        $match = [];

        while (preg_match('/\n{3,}/', $content, $match, PREG_OFFSET_CAPTURE))
        {
            list($string, $offset) = $match[0];
            $this->parent->mutate(
                "Removing excessing whitespace",
                fn(AnalysisText $text) => $text->replaceSubstring($offset, $offset + strlen($string), "\n\n")
            );
            $content = $this->parent->getActiveAnalyser()->getProcessedContent();
        }

        while (preg_match('/\{\n{2,}/', $content, $match, PREG_OFFSET_CAPTURE))
        {
            list($string, $offset) = $match[0];
            $this->parent->mutate(
                "Removing excessing whitespace after bracket",
                fn(AnalysisText $text) => $text->replaceSubstring($offset, $offset + strlen($string), "{\n")
            );
            $content = $this->parent->getActiveAnalyser()->getProcessedContent();
        }

        while (preg_match('/\n{2,}([^\n]+)?\}/', $content, $match, PREG_OFFSET_CAPTURE))
        {
            list($string, $offset) = $match[0];
            $this->parent->mutate(
                "Removing excessing whitespace before bracket",
                fn(AnalysisText $text) => $text->replaceSubstring($offset, $offset + strlen($string), "\n".($match[1][0] ?? '')."}")
            );
            $content = $this->parent->getActiveAnalyser()->getProcessedContent();
        }
    }
}