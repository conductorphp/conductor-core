<?php

namespace ConductorCore\Filesystem\MountManager;

interface MountManagerAwareInterface
{
    public function setMountManager(MountManager $mountManager): void;
}
