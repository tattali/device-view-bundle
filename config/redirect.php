<?php

declare(strict_types=1);

use DeviceViewBundle\EventListener\DeviceRedirectListener;
use DeviceViewBundle\Redirect\RedirectPolicy;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

// The redirect of each view to the host it is served on: the policy that decides it, also asked
// by the switch listener (see switch.php), and the listener of the device redirect.
// DeviceViewBundle::loadExtension() sets the arguments that come from the configuration.
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.redirect_policy', RedirectPolicy::class)
            ->args([
                new AbstractArgument('the enabled redirect.<view> options'),
                new Reference('device_view.view_names'),
                new Reference('device_view.switch_url_generator'),
                new AbstractArgument('the remember cookie name when it has no domain'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])

        ->set('device_view.device_redirect_listener', DeviceRedirectListener::class)
            ->args([
                new Reference('device_view.redirect_policy'),
                new Reference('device_view.resolver'),
                new AbstractArgument('redirect.methods'),
                new AbstractArgument('redirect.cache_control'),
                new Reference('device_view.switch_parameter'),
                new Reference('device_view.route_defaults'),
            ])
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => 30])
    ;
};
