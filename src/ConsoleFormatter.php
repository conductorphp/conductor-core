<?php

namespace ConductorCore;

use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Formats incoming records for console output by coloring them depending on log level.
 */
class ConsoleFormatter implements FormatterInterface
{
    private static array $levelColorMap
        = [
            Logger::DEBUG => 'fg=cyan',
            Logger::INFO => 'fg=white',
            Logger::NOTICE => 'fg=yellow',
            Logger::WARNING => 'fg=yellow',
            Logger::ERROR => 'fg=white;bg=red',
            Logger::CRITICAL => 'fg=white;bg=red',
            Logger::ALERT => 'fg=white;bg=red',
            Logger::EMERGENCY => 'fg=white;bg=red',
        ];

    private array $options
        = [
            'format' => "%start_tag%[%datetime%] %level_name% %message%%end_tag%%context%%extra%\n",
            'date_format' => 'H:i:s',
            'colors' => true,
            'multiline' => true,
        ];

    public function __construct($options = [])
    {
        $this->options = array_replace($this->options, $options);
    }

    public function formatBatch(array $records): array
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->format($record);
        }

        return $records;
    }

    public function format(LogRecord $record): string
    {
        $record = $this->replacePlaceHolder($record);

        $levelColor = self::$levelColorMap[$record['level']];

        $separator = ($this->options['multiline']) ? "\n" : ' ';
        $context = $extra = '';
        if ($record['context']) {
            $context = $separator . $this->dumpData($record['context']);
        }
        if ($record['extra']) {
            $extra = $separator . $this->dumpData($record['extra']);
        }

        return strtr(
            $this->options['format'],
            [
                '%datetime%' => $record['datetime']->format($this->options['date_format']),
                '%start_tag%' => sprintf('<%s>', $levelColor),
                '%level_name%' => sprintf('%-9s', $record['level_name']),
                '%end_tag%' => '</>',
                '%channel%' => $record['channel'],
                '%message%' => trim($this->replacePlaceHolder($record)['message']),
                '%context%' => $context,
                '%extra%' => $extra,
            ]
        );
    }

    private function replacePlaceHolder(LogRecord $record): LogRecord
    {
        $message = $record['message'];

        if (!str_contains($message, '{')) {
            return $record;
        }

        $context = $record['context'];

        $replacements = [];
        foreach ($context as $k => $v) {
            // Remove quotes added by the dumper around string.
            $v = trim($this->dumpData($v), '"');
            $v = OutputFormatter::escape($v);
            $replacements['{' . $k . '}'] = sprintf('<comment>%s</comment>', $v);
        }

        $record['formatted'] = strtr($message, $replacements);
        return $record;
    }

    private function dumpData(mixed $data): string
    {
        return print_r($data, true);
    }
}
