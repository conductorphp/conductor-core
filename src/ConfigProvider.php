<?php

namespace DevopsToolCore;

class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     *
     * @return array
     */
    public function __invoke()
    {
        return [
            'console'      => $this->getConsoleConfig(),
            'dependencies' => $this->getDependencyConfig(),
            'filesystem'   => $this->getFilesystemConfig(),
        ];
    }

    /**
     * Returns the container dependencies
     *
     * @return array
     */
    private function getDependencyConfig()
    {
        return require(__DIR__ . '/../config/dependencies.php');
    }

    /**
     * @return array
     */
    private function getConsoleConfig()
    {
        return require(__DIR__ . '/../config/console.php');
    }

    /**
     * @return array
     */
    private function getFilesystemConfig()
    {
        return require(__DIR__ . '/../config/filesystem.php');
    }

}
