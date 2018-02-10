<?php
/**
 * @author Kirk Madera <kmadera@robofirm.com>
 */

namespace ConductorCore\Filesystem;

use League\Flysystem\AdapterInterface;

interface FilesystemInterface extends \League\Flysystem\FilesystemInterface
{
    /**
     * @return AdapterInterface
     */
    public function getAdapter(): AdapterInterface;
}
