<?php

declare(strict_types=1);

namespace Efabrica\NetteElasticAmp\Helper;

class Stacktrace
{
    public static function generate(array $trace, array $ignoredClasses = []): array
    {
        $source = [];
        foreach ($trace as $row) {
            if (
                isset($row['line'])
                && (isset($row['file']) && is_file($row['file']))
                && (isset($row['class']) && !in_array($row['class'], $ignoredClasses))
                && !strpos($row['file'], 'nette.configurator')
            ) {
                $detail = Stacktrace::generateFileDetail($row['file'], $row['line']);

                $source[] = [
                    'filename' => $row['file'],
                    'lineno' => (int) $row['line'],
                    'library_frame' => strpos($row['file'], '/vendor/') ? true : false,
                    'pre_context' => $detail['pre'],
                    'context_line' => $detail['line'],
                    'post_context' => $detail['post'],
                ];
            }
        }

        return $source;
    }

    private static function generateFileDetail(string $file, int $line, int $linesBefore = 5, int $linesAfter = 5): array
    {
        if (!is_file($file)) {
            return ['pre' => '', 'line' => '', 'post' => ''];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return ['pre' => '', 'line' => 'Unable to parse file', 'post' => ''];
        }

        $fixedLine = $line - 1;
        return [
            'pre' => array_slice($lines, max(0, $fixedLine - $linesBefore), $line - $linesBefore > 0 ? $linesBefore : $line - 1),
            'line' => $lines[$fixedLine],
            'post' => array_slice($lines, min(count($lines), $fixedLine + 1), $line + $linesAfter < count($lines) ? $linesAfter : null)
        ];
    }
}
