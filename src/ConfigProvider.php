<?php

namespace ConductorCore;

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
            'console' => $this->getConsoleConfig(),
            'dependencies' => $this->getDependencyConfig(),
            'filesystem' => $this->getFilesystemConfig(),
            'shell' => $this->getShellConfig(),
        ];
    }

    /**
     * Returns the container dependencies
     *
     * @return array
     */
    private function getDependencyConfig(): array
    {
        return require(__DIR__ . '/../config/dependencies.php');
    }

    /**
     * @return array
     */
    private function getConsoleConfig(): array
    {
        return require(__DIR__ . '/../config/console.php');
    }

    /**
     * @return array
     */
    private function getFilesystemConfig(): array
    {
        return require(__DIR__ . '/../config/filesystem.php');
    }

    /**
     * @return array
     */
    private function getShellConfig(): array
    {
        return require(__DIR__ . '/../config/shell.php');
    }

}
