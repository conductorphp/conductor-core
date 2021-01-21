<?php
/**
 * @author Kirk Madera <kirk.madera@rmgmedia.com>
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
