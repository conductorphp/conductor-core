<?php

namespace ConductorCore;

use Generator;
use Laminas\ConfigAggregator\GlobTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Provide a collection of Yaml files returning config arrays.
 */
class YamlFileProvider
{
    use GlobTrait;

    private string $pattern;

    /**
     * @param string $pattern A glob pattern by which to look up config files.
     */
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    public function __invoke(): Generator
    {
        foreach ($this->glob($this->pattern) as $file) {
            yield Yaml::parse(file_get_contents($file)) ?? [];
        }
    }
}
