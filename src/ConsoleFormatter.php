<?php

/*
 * Based on \Symfony\Bridge\Monolog\Formatter\ConsoleFormatter by Fabien Potencier <fabien@symfony.com>
 *
 * Overrode to adjust default formatting to be closer to that of Monolog's defaults.
 */

namespace DevopsToolCore;

use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Formats incoming records for console output by coloring them depending on log level.
 *
 * @author Kirk Madera <kmadera@robofirm.com>
 */
class ConsoleFormatter implements FormatterInterface
{
    private static $levelColorMap
        = array(
            Logger::DEBUG     => 'fg=cyan',
            Logger::INFO      => 'fg=white',
            Logger::NOTICE    => 'fg=yellow',
            Logger::WARNING   => 'fg=yellow',
            Logger::ERROR     => 'fg=white;bg=red',
            Logger::CRITICAL  => 'fg=white;bg=red',
            Logger::ALERT     => 'fg=white;bg=red',
            Logger::EMERGENCY => 'fg=white;bg=red',
        );

    private $options
        = [
            'format'      => "%start_tag%[%datetime%] %level_name% %message%%end_tag%%context%%extra%\n",
            'date_format' => 'H:i:s',
            'colors'      => true,
            'multiline'   => true,
        ];

    /**
     * ConsoleFormatter constructor.
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        $this->options = array_replace($this->options, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->format($record);
        }

        return $records;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
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

        $formatted = strtr(
            $this->options['format'],
            array(
                '%datetime%'   => $record['datetime']->format($this->options['date_format']),
                '%start_tag%'  => sprintf('<%s>', $levelColor),
                '%level_name%' => sprintf('%-9s', $record['level_name']),
                '%end_tag%'    => '</>',
                '%channel%'    => $record['channel'],
                '%message%'    => trim($this->replacePlaceHolder($record)['message']),
                '%context%'    => $context,
                '%extra%'      => $extra,
            )
        );

        return $formatted;
    }

    private function replacePlaceHolder(array $record)
    {
        $message = $record['message'];

        if (false === strpos($message, '{')) {
            return $record;
        }

        $context = $record['context'];

        $replacements = array();
        foreach ($context as $k => $v) {
            // Remove quotes added by the dumper around string.
            $v = trim($this->dumpData($v), '"');
            $v = OutputFormatter::escape($v);
            $replacements['{'.$k.'}'] = sprintf('<comment>%s</comment>', $v);
        }

        $record['message'] = strtr($message, $replacements);
        return $record;
    }

    private function dumpData($data)
    {
        return print_r($data, true);
    }
}
