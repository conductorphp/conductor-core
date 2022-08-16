<?php

namespace ConductorCore\Shell\Adapter;

interface ShellAdapterAwareInterface
{
    public function setShellAdapter(ShellAdapterInterface $shellAdapter): void;
}
