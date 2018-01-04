<?php

namespace DevopsToolCore\Filesystem;

use DevopsToolCore\Exception\RuntimeException;
use Aws\Credentials\Credentials;
use DevopsToolCore\ShellCommandHelper;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @todo Deprecate this class
 */
class AwsS3FilesystemTransfer implements FilesystemTransferInterface
{
    /**
     * @var LocalAdapter
     */
    private $sourceFilesystem;
    /**
     * @var FilesystemInterface
     */
    private $destinationFilesystem;
    /**
     * @var ShellCommandHelper
     */
    private $shellCommandHelper;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        FilesystemInterface $sourceFilesystem,
        FilesystemInterface $destinationFilesystem,
        ShellCommandHelper $shellCommandHelper = null,
        LoggerInterface $logger = null
    ) {
        /** @var AdapterInterface $sourceAdapter */
        $sourceAdapter = $sourceFilesystem->getAdapter();
        /** @var AdapterInterface $destinationAdapter */
        $destinationAdapter = $destinationFilesystem->getAdapter();

        if (!($sourceAdapter instanceof AwsS3Adapter || $sourceAdapter instanceof LocalAdapter)) {
            throw new Exception(
                '$sourceAdapter must be an instance of League\Flysystem\Adapter\Local or League\Flysystem\AwsS3v3\AwsS3Adapter.'
            );
        }

        if (!($destinationAdapter instanceof AwsS3Adapter || $destinationAdapter instanceof LocalAdapter)) {
            throw new Exception(
                '$destinationAdapter must be an instance of League\Flysystem\Adapter\Local or League\Flysystem\AwsS3v3\AwsS3Adapter.'
            );
        }

        if (!($sourceAdapter instanceof AwsS3Adapter || $destinationAdapter instanceof AwsS3Adapter)) {
            throw new Exception(
                'At least one of $sourceAdapter or $destinationAdapter must be an instance of League\Flysystem\AwsS3v3\AwsS3Adapter.'
            );
        }

        $this->sourceFilesystem = $sourceFilesystem;
        $this->destinationFilesystem = $destinationFilesystem;
        if (is_null($shellCommandHelper)) {
            $shellCommandHelper = new ShellCommandHelper();
        }
        $this->shellCommandHelper = $shellCommandHelper;
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @param string $sourcePath
     * @param string $destinationPath
     * @param array  $excludes
     * @param array  $includes
     * @param bool   $delete
     * @param bool   $followSymlinks
     *
     * @throws RuntimeException if source is not a readable file or destination path is not a writable file
     * @return void
     */
    public function sync(
        $sourcePath,
        $destinationPath,
        array $excludes = [],
        array $includes = [],
        $delete = false,
        $followSymlinks = false
    ) {
        if (!$this->sourceFilesystem->has($sourcePath)) {
            throw new RuntimeException(
                sprintf(
                    'File "%s" does not exist on the source filesystem (%s).',
                    $sourcePath,
                    get_class($this->sourceFilesystem->getAdapter())
                )
            );
        }

        $this->logger->debug(
            sprintf(
                'Syncing "%s" on source filesystem to "%s" on destination filesystem.',
                $sourcePath,
                $destinationPath
            )
        );

        $sourceAdapter = $this->sourceFilesystem->getAdapter();
        $destinationAdapter = $this->destinationFilesystem->getAdapter();

        $anyS3Adapter = ($sourceAdapter instanceof AwsS3Adapter) ? $sourceAdapter : $destinationAdapter;
        if ($this->hasAwsCli() && $profile = $this->getAwsProfile($anyS3Adapter)) {
            $sourcePath = $this->getFilepathForCommand($sourcePath, $sourceAdapter);
            $destinationPath = $this->getFilepathForCommand($destinationPath, $destinationAdapter);
            $command = 'aws s3 sync ' . escapeshellarg($sourcePath) . ' ' . escapeshellarg($destinationPath) . ' '
                . '--profile ' . escapeshellarg($profile) . ' ';

            if ($excludes) {
                foreach ($excludes as $rule) {
                    $rule = $this->convertExcludeIncludeRuleFromRsyncToAwsFormat($rule);
                    $command .= '--exclude=' . escapeshellarg($rule) . ' ';
                }
            }

            if ($includes) {
                foreach ($includes as $rule) {
                    $rule = $this->convertExcludeIncludeRuleFromRsyncToAwsFormat($rule);
                    $command .= '--include=' . escapeshellarg($rule) . ' ';
                }
            }

            if ($delete) {
                $command .= '--delete ';
            }

            if (!$followSymlinks) {
                $command .= '--no-follow-symlink ';
            }

            // Redirect output to stderr. This command outputs each file as it is synced to stdout. Sending this to
            // stderr allows the shellCommandHelper to output it as it's written.
            $command .= '1>&2';

            $this->shellCommandHelper->runShellCommand($command);
        } else {
            throw new Exception('Sync with fallback adapter not yet implemented.');
        }
    }

    /**
     * @param string $sourcePath
     * @param string $destinationPath
     *
     * @throws RuntimeException if source is not a readable file or destination path is not a writable file
     * @return void
     */
    public function copy(
        $sourcePath,
        $destinationPath
    ) {

        if (!$this->sourceFilesystem->has($sourcePath)) {
            throw new RuntimeException(
                sprintf(
                    'File "%s" does not exist on the source filesystem (%s).',
                    $sourcePath,
                    get_class($this->sourceFilesystem->getAdapter())
                )
            );
        }

        $this->logger->debug(
            sprintf(
                'Copying "%s" on source filesystem to "%s" on destination filesystem.',
                $sourcePath,
                $destinationPath
            )
        );

        $this->destinationFilesystem->putStream($destinationPath, $this->sourceFilesystem->readStream($sourcePath));
    }

    /**
     * @return FilesystemInterface
     */
    public function getSourceFilesystem()
    {
        return $this->sourceFilesystem;
    }

    /**
     * @return FilesystemInterface
     */
    public function getDestinationFilesystem()
    {
        return $this->destinationFilesystem;
    }

    /**
     * Checks if aws cli tool is available in the path
     *
     * @return bool
     */
    protected function hasAwsCli()
    {
        try {
            $this->shellCommandHelper->runShellCommand('which aws &> /dev/null');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gets aws profile from ~/.aws/credentials file that matches the current S3 adapter
     *
     * @param AwsS3Adapter $s3Adapter
     *
     * @return bool
     */
    private function getAwsProfile(AwsS3Adapter $s3Adapter)
    {
        $home = getenv('HOME');
        if (!is_readable("$home/.aws/credentials")) {
            return false;
        }
        $credentials = parse_ini_file("$home/.aws/credentials", true);
        if (!$credentials) {
            return false;
        }
        $profile = false;
        /** @var PromiseInterface $promise */
        $promise = $s3Adapter->getClient()->getCredentials()->then(
            function (Credentials $value) use ($credentials, &$profile) {
                $accessKey = $value->getAccessKeyId();
                foreach ($credentials as $section => $auth) {
                    if (isset($auth['aws_access_key_id']) && $accessKey == $auth['aws_access_key_id']) {
                        $profile = $section;
                        break;
                    }
                }
            }
        );
        $promise->wait();
        return $profile;
    }

    /**
     * @todo How to best handle excludes? Is rsync excludes format a good default here? Should it be an option?
     *
     * @param string $rule
     *
     * @return string
     */
    private function convertExcludeIncludeRuleFromRsyncToAwsFormat($rule)
    {
        if ('/' == substr($rule, 0, 1)) {
            $rule = substr($rule, 1);
        } else {
            $rule = '*' . $rule;
        }
        return $rule . '*';
    }

    /**
     * @param string $path
     * @param AdapterInterface $fileAdapter
     *
     * @return string
     */
    private function getFilepathForCommand($path, AdapterInterface $fileAdapter)
    {
        $path = rtrim($path, '*');
        if ($fileAdapter instanceof AwsS3Adapter) {
            /** @var AwsS3Adapter $fileAdapter */
            $path = 's3://' . $fileAdapter->getBucket() . '/' . $fileAdapter->getPathPrefix() . $path;
            return $path;
        } elseif ($fileAdapter instanceof LocalAdapter) {
            /** @var LocalAdapter $fileAdapter */
            $path = $fileAdapter->getPathPrefix() . $path;
            return $path;
        } else {
            throw new Exception(
                '$fileAdapter must be an instance of League\Flysystem\AwsS3v3\AwsS3Adapter or League\Flysystem\Adapter\Local.'
            );
        }
    }

}
