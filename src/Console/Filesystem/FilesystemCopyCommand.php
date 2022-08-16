<?php

namespace ConductorCore\Console\Filesystem;

use ConductorCore\Filesystem\MountManager\MountManager;
use ConductorCore\MonologConsoleHandlerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FilesystemCopyCommand extends Command
{
    use MonologConsoleHandlerAwareTrait;

    private MountManager $mountManager;
    private LoggerInterface $logger;

    public function __construct(
        MountManager     $mountManager,
        ?LoggerInterface $logger = null,
        ?string          $name = null
    ) {
        $this->mountManager = $mountManager;
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $filesystemAdapterNames = $this->mountManager->getFilesystemPrefixes();
        $this->setName('filesystem:copy')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                "Source path in the format {adapter}://{path}.\nAvailable Adapters: <comment>" . implode(
                    ', ',
                    $filesystemAdapterNames
                )
                . '</comment>'
            )
            ->addArgument(
                'destination',
                InputArgument::REQUIRED,
                "Destination path in the format {adapter}://{path}.\nAvailable Adapters: <comment>" . implode(
                    ', ',
                    $filesystemAdapterNames
                )
                . '</comment>'
            )
            ->setDescription(
                'Copy a single file from a source filesystem directory to a destination filesystem directory.'
            )
            ->setHelp(
                "This command copies a single file from a source filesystem directory to a destination filesystem directory."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->injectOutputIntoLogger($output, $this->logger);
        $this->mountManager->setWorkingDirectory(getcwd());
        $source = $input->getArgument('source');
        $destination = $input->getArgument('destination');

        [$prefix, $arguments] = $this->mountManager->filterPrefix([$source]);
        $source = "$prefix://{$arguments[0]}";

        [$prefix, $arguments] = $this->mountManager->filterPrefix([$destination]);
        $destination = "$prefix://{$arguments[0]}";

        $this->mountManager->copy($source, $destination);
        return self::SUCCESS;
    }

}
