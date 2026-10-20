<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

// The configuration tree under the device_view root. Each feature adds its own keys.
return static function (DefinitionConfigurator $definition): void {
    $definition->rootNode()
        ->children()
            ->arrayNode('detector')
                ->info('How the device type is detected when nothing else decides the view.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->stringNode('id')
                        ->info('auto (Mobile Detect when it is installed), mobile_detect, or the id of a service implementing DeviceViewBundle\Detector\DeviceDetectorInterface.')
                        ->defaultValue('auto')
                        ->cannotBeEmpty()
                    ->end()
                    ->arrayNode('mobile_detect')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('headers')
                                ->info('The request headers passed to Mobile Detect, also listed in Vary.')
                                ->defaultValue(['User-Agent'])
                                ->stringPrototype()->cannotBeEmpty()->end()
                                ->validate()
                                    ->ifEmpty()
                                    ->thenInvalid('device_view.detector.mobile_detect.headers must list at least one header.')
                                ->end()
                            ->end()
                            ->scalarNode('cache')
                                ->info('The id of a PSR-16 cache service (Psr\SimpleCache\CacheInterface) for Mobile Detect\'s results, or null.')
                                ->defaultNull()
                                ->validate()
                                    ->ifTrue(static fn (mixed $cache): bool => null !== $cache && (!is_string($cache) || '' === $cache))
                                    ->thenInvalid('device_view.detector.mobile_detect.cache must be null or a service id, got %s.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
    ;
};
