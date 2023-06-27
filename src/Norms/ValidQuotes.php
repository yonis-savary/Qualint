<?php

namespace YonisSavary\Qualint\Norms;

use YonisSavary\Barn\Analysis\Analyser;
use YonisSavary\Barn\Analysis\AnalysisText;
use YonisSavary\Qualint\AbstractNorm;

class ValidQuotes extends AbstractNorm
{
    public function checkFile(Analyser $analyser)
    {
        $strings = $analyser->getStrings();
        for ($i=0; $i<count($strings); $i++)
        {
            $string = $strings[$i];

            $start = $string->startOffset;
            $end = $string->endOffset;
            $string = $string->content;

            $isDoubleQuote = str_starts_with($string, '"');

            $stringExclusiveContent = '/\\$\w+|\\\\[ntr]/';

            if ($isDoubleQuote)
            {
                // Double quote: if no variables, escaped chars... we tranform
                if (!preg_match($stringExclusiveContent, $string))
                {
                    $string = str_replace('\'', '\\\'', $string);
                    $string = preg_replace('/^"|"$/', '\'', $string);
                    $string = str_replace('\"', '"', $string);
                    $this->parent->mutate(
                        "Replacing useless double quote with [$string]",
                        fn(AnalysisText &$text) => $text->replaceSubstring($start, $end, $string),
                        offset: $start
                    );
                }
            }
            else
            {
                $isRegex = preg_match('/\'\/.+?\/\'/', $string);
                if ($isRegex && !preg_match('/\\$\w+/', $string))
                    continue;

                // Single quote: if variables, escaped chars... we tranform
                if (preg_match($stringExclusiveContent, $string))
                {
                    $string = str_replace('"', '\\"', $string);
                    $string = preg_replace('/^\'|\'$/', '"', $string);
                    $string = str_replace('\\\'', '\'', $string);
                    $this->parent->mutate(
                        "Replacing ambiguous single quote with [$string]",
                        fn(AnalysisText &$text) => $text->replaceSubstring($start, $end, $string),
                        offset: $start
                    );
                }
            }

            $strings = $analyser->getStrings();
        }
    }
}