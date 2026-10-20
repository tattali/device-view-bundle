<?php

declare(strict_types=1);

use DeviceViewBundle\Routing\RouteDefaults;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

// The per-route overrides: the reader of the route defaults of the matched route, asked by the
// switch, device redirect and remember listeners (see switch.php, redirect.php and remember.php).
// DeviceViewBundle::loadExtension() sets the arguments that come from the configuration.
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.route_defaults', RouteDefaults::class)
            ->args([
                new AbstractArgument('route_defaults.redirect'),
                new AbstractArgument('route_defaults.remember'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
    ;
};
