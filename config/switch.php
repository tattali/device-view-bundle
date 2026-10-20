<?php

declare(strict_types=1);

use DeviceViewBundle\EventListener\SwitchListener;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

// The switch query parameter: its reader (also a step of the resolver, see resolver.php), the
// URLs of the switch, and the listener that redirects after a switch, to the host of the view
// when it is served on another host (see redirect.php), as the route defaults of the matched
// route allow (see route_defaults.php).
// DeviceViewBundle::loadExtension() sets the arguments that come from the configuration.
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.switch_parameter', SwitchParameter::class)
            ->args([
                new Reference('device_view.view_names'),
                new AbstractArgument('switch.parameter'),
                new AbstractArgument('switch.enabled'),
                new AbstractArgument('redirect.methods'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])

        ->set('device_view.switch_url_generator', SwitchUrlGenerator::class)
            ->args([new AbstractArgument('switch.parameter')])

        ->set('device_view.switch_listener', SwitchListener::class)
            ->args([
                new Reference('device_view.switch_parameter'),
                new Reference('device_view.switch_url_generator'),
                new AbstractArgument('switch.target'),
                new AbstractArgument('switch.status'),
                new AbstractArgument('redirect.cache_control'),
                new Reference('device_view.redirect_policy'),
                new Reference('device_view.resolver'),
                new Reference('device_view.remember_policy'),
                new Reference('device_view.route_defaults'),
            ])
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => 31])
    ;
};
