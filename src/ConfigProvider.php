<?php

namespace ConductorCore;

class ConfigProvider
{
    /**
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke(): array
    {
        return [
            'console' => $this->getConsoleConfig(),
            'dependencies' => $this->getDependencyConfig(),
            'filesystem' => $this->getFilesystemConfig(),
            'shell' => $this->getShellConfig(),
        ];
    }

    private function getDependencyConfig(): array
    {
        return require(__DIR__ . '/../config/dependencies.php');
    }

    private function getConsoleConfig(): array
    {
        return require(__DIR__ . '/../config/console.php');
    }

    private function getFilesystemConfig(): array
    {
        return require(__DIR__ . '/../config/filesystem.php');
    }

    private function getShellConfig(): array
    {
        return require(__DIR__ . '/../config/shell.php');
    }

}
