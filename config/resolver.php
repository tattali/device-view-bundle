<?php

declare(strict_types=1);

use DeviceViewBundle\DeviceView;
use DeviceViewBundle\EventListener\ContextListener;
use DeviceViewBundle\Resolver\Step\DetectorStep;
use DeviceViewBundle\Resolver\Step\ForcedStep;
use DeviceViewBundle\Resolver\Step\SwitchStep;
use DeviceViewBundle\Resolver\ViewResolver;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\View\ViewNames;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

// The per-request context, the lazy resolution of the view and the DeviceView facade.
// DeviceViewBundle::loadExtension() sets the arguments that come from the configuration, and
// device_view.resolver, an alias of device_view.resolver.default or of services.resolver.
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.view_names', ViewNames::class)
            ->args([new AbstractArgument('views.names')])

        ->set('device_view.context_listener', ContextListener::class)
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => 33])

        // the sources of the resolution order (design.md section 5), each step in its place
        ->set('device_view.resolver.step.forced', ForcedStep::class)
        ->set('device_view.resolver.step.switch', SwitchStep::class)
            ->args([new Reference('device_view.switch_parameter')])
        // device_view.resolver.step.remembered is in remember.php
        ->set('device_view.resolver.step.detector', DetectorStep::class)
            ->args([new Reference('device_view.detector')])

        ->set('device_view.resolver.default', ViewResolver::class)
            ->args([
                [
                    new Reference('device_view.resolver.step.forced'),
                    new Reference('device_view.resolver.step.switch'),
                    new Reference('device_view.resolver.step.remembered'),
                    new Reference('device_view.resolver.step.detector'),
                ],
                new AbstractArgument('default_view'),
                new AbstractArgument('tablet_as_mobile'),
            ])
        ->alias(ViewResolverInterface::class, 'device_view.resolver')

        ->set('device_view.device_view', DeviceView::class)
            ->args([
                new Reference('request_stack'),
                new Reference('device_view.resolver'),
                new Reference('device_view.view_names'),
                new Reference('device_view.switch_url_generator'),
                new AbstractArgument('default_view after tablet_as_mobile'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
        ->alias(DeviceView::class, 'device_view.device_view')
    ;
};
