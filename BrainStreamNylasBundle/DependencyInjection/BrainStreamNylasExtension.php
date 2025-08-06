<?php

/**
 * BrainStream Nylas Extension.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\DependencyInjection
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * BrainStream Nylas Extension.
 *
 * Extension for configuring the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\DependencyInjection
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class BrainStreamNylasExtension extends Extension
{
    /**
     * Load the extension configuration.
     *
     * @param array            $configs   The configs
     * @param ContainerBuilder $container The container builder
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        if (isset($config['settings'])) {
            $container->prependExtensionConfig($this->getAlias(), ['settings' => $config['settings']]);
        }
    }

    /**
     * Get the extension alias.
     *
     * @return string
     */
    #[\Override]
    public function getAlias(): string
    {
        return 'brainstream_nylas';
    }
}
