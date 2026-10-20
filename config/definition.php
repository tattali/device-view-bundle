<?php

declare(strict_types=1);

use DeviceViewBundle\View\DeviceType;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

// The configuration tree under the device_view root. Each feature adds its own keys.
return static function (DefinitionConfigurator $definition): void {
    $views = array_map(static fn (DeviceType $view): string => $view->value, DeviceType::cases());
    $names = $definition->rootNode()
        ->children()
            ->enumNode('default_view')
                ->info('The view when nothing decides it: no detector answer, an empty user agent, or no request.')
                ->values($views)
                ->defaultValue(DeviceType::Desktop->value)
            ->end()
            ->booleanNode('tablet_as_mobile')
                ->info('Serve the mobile view wherever the tablet view was resolved.')
                ->defaultFalse()
            ->end()
            ->arrayNode('views')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('names')
                        ->info('The name of each view in URLs, the remember storage, headers and the DeviceView facade: unique, lowercase letters, digits, "_" and "-".')
                        ->addDefaultsIfNotSet()
                        ->validate()
                            ->always(
                                static function (array $names): array {
                                    // each name is a string: the stringNode children reject anything else
                                    foreach (array_count_values(array_filter($names, is_string(...))) as $name => $count) {
                                        if ($count > 1) {
                                            throw new InvalidArgumentException(sprintf('The view names must be unique: "%s" is used twice.', $name));
                                        }
                                    }

                                    return $names;
                                },
                            )
                        ->end()
                        ->children()
    ;
    foreach ($views as $view) {
        $names
            ->stringNode($view)
                ->defaultValue($view)
                ->validate()
                    ->always(static function (string $name): string {
                        if (1 !== preg_match('/^[a-z0-9_-]+$/D', $name)) {
                            throw new InvalidArgumentException(sprintf('The view name "%s" is invalid: use lowercase letters, digits, "_" and "-" only.', $name));
                        }

                        return $name;
                    })
                ->end()
            ->end()
        ;
    }

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

    $definition->rootNode()
        ->children()
            ->arrayNode('services')
                ->info('Replace a built-in service with the id of your own service.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('resolver')
                        ->info('The id of a service implementing DeviceViewBundle\Resolver\ViewResolverInterface, or null for the built-in resolver.')
                        ->defaultNull()
                        ->validate()
                            ->ifTrue(static fn (mixed $id): bool => null !== $id && (!is_string($id) || '' === $id))
                            ->thenInvalid('device_view.services.resolver must be null or a service id, got %s.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
    ;
};
