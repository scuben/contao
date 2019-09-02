<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\DependencyInjection\Compiler;

use Contao\CoreBundle\Cron\ContaoCron;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TaggedCronsPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ContaoCron::class)) {
            return;
        }

        $serviceIds = $container->findTaggedServiceIds('contao.cron');
        $definition = $container->findDefinition(ContaoCron::class);

        foreach ($serviceIds as $serviceId => $tags) {
            if ($container->hasAlias($serviceId)) {
                $serviceId = (string) $container->getAlias($serviceId);
            }

            foreach ($tags as $attributes) {
                if (!isset($attributes['interval'])) {
                    throw new InvalidConfigurationException(
                        sprintf('Missing interval attribute in tagged cron service with service id "%s"', $serviceId)
                    );
                }

                $method = $this->getMethod($attributes);
                $interval = $attributes['interval'];
                $priority = (int) ($attributes['priority'] ?? 0);
                $cli = (bool) ($attributes['cli'] ?? false);

                $definition->addMethodCall('addCronJob', [$serviceId, $method, $interval, $priority, $cli]);
            }

            $container->findDefinition($serviceId)->setPublic(true);
        }
    }

    private function getMethod(array $attributes): string
    {
        if (isset($attributes['method'])) {
            return (string) $attributes['method'];
        }

        return 'on'.ucfirst($attributes['interval']);
    }
}
