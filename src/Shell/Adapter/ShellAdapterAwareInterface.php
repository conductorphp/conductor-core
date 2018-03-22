<?php
/**
 * @author Kirk Madera <kmadera@robofirm.com>
 */

namespace ConductorCore\Shell\Adapter;

/**
 * Class ShellAdapterAwareInterface
 *
 * @package ConductorCore
 */
interface ShellAdapterAwareInterface
{
    /**
     * @param ShellAdapterInterface $shellAdapter
     */
    public function setShellAdapter(ShellAdapterInterface $shellAdapter): void;
}
