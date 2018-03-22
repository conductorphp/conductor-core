<?php

namespace ConductorCore;

use Symfony\Component\Yaml\Yaml;
use Zend\ConfigAggregator\GlobTrait;

/**
 * Provide a collection of Yaml files returning config arrays.
 */
class YamlFileProvider
{
    use GlobTrait;

    /** @var string */
    private $pattern;

    /**
     * @param string $pattern A glob pattern by which to look up config files.
     */
    public function __construct($pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * @return \Generator
     */
    public function __invoke()
    {
        foreach ($this->glob($this->pattern) as $file) {
            yield Yaml::parse(file_get_contents($file));
        }
    }
}
