<?php

/**
 * BrainStream Nylas Bundle.
 *
 * Main bundle class for Nylas email integration with OroCRM.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle;

use BrainStream\Bundle\NylasBundle\DependencyInjection\BrainStreamNylasExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * BrainStream Nylas Bundle.
 *
 * Main bundle class for Nylas email integration with OroCRM.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class BrainStreamNylasBundle extends Bundle
{
    /**
     * Constructor for BrainStreamNylasBundle.
     */
    public function __construct()
    {
        // Bundle initialization
    }

    /**
     * Get the container extension for this bundle.
     *
     * @return ExtensionInterface|null
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new BrainStreamNylasExtension();
    }

    /**
     * Build the bundle.
     *
     * @param ContainerBuilder $container The container builder
     *
     * @return void
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }
}
