<?php
/**
 * @author Kirk Madera <kmadera@robofirm.com>
 */

namespace DevopsToolCore\Filesystem;

use DevopsToolCore\ShellCommandHelper;
use Exception;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Psr\Log\LoggerInterface;

/**
 * @todo Deprecate this class
 */
class FilesystemTransferFactory
{
    /**
     * @param FilesystemInterface     $sourceFilesystem
     * @param FilesystemInterface     $destinationFilesystem
     * @param ShellCommandHelper|null $shellCommandHelper
     * @param LoggerInterface|null    $logger
     *
     * @return FilesystemTransferInterface
     * @throws Exception
     */
    public static function create(
        FilesystemInterface $sourceFilesystem,
        FilesystemInterface $destinationFilesystem,
        ShellCommandHelper $shellCommandHelper = null,
        LoggerInterface $logger = null
    ) {
        $sourceAdapter = $sourceFilesystem->getAdapter();
        $destinationAdapter = $destinationFilesystem->getAdapter();

        if (($sourceAdapter instanceof Local && $destinationAdapter instanceof AwsS3Adapter)
            || ($sourceAdapter instanceof AwsS3Adapter && $destinationAdapter instanceof AwsS3Adapter)
            || ($sourceAdapter instanceof AwsS3Adapter && $destinationAdapter instanceof Local)
        ) {
            return new AwsS3FilesystemTransfer(
                $sourceFilesystem,
                $destinationFilesystem,
                $shellCommandHelper,
                $logger
            );
        }

        throw new Exception(
            sprintf(
                'Unsupported source filesystem adapter "%s" with destination filesystem adapter "%s".',
                get_class($sourceAdapter),
                get_class($destinationAdapter)
            )
        );
    }
}
