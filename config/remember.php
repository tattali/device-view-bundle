<?php

declare(strict_types=1);

use DeviceViewBundle\EventListener\RememberListener;
use DeviceViewBundle\Remember\CookieStorage;
use DeviceViewBundle\Remember\NullStorage;
use DeviceViewBundle\Remember\RememberPolicy;
use DeviceViewBundle\Remember\SessionStorage;
use DeviceViewBundle\Remember\ViewStorageInterface;
use DeviceViewBundle\Resolver\Step\RememberedStep;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

// Remembering the view: the built-in storages, the reader of the storage (a step of the
// resolver, see resolver.php), the remember policy and the listener that writes on the response,
// after BeforeRememberEvent.
// DeviceViewBundle::loadExtension() sets the arguments that come from the configuration, and
// device_view.storage, an alias of the storage remember.storage names.
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.storage.cookie', CookieStorage::class)
            ->args([
                new AbstractArgument('remember.cookie.name'),
                new AbstractArgument('remember.cookie.path'),
                new AbstractArgument('remember.cookie.domain'),
                new AbstractArgument('remember.cookie.secure, null for auto'),
                new AbstractArgument('remember.cookie.http_only'),
                new AbstractArgument('remember.cookie.same_site'),
                new AbstractArgument('remember.cookie.raw'),
            ])
        ->set('device_view.storage.session', SessionStorage::class)
            ->args([new AbstractArgument('remember.session.key')])
        ->set('device_view.storage.none', NullStorage::class)
        ->alias(ViewStorageInterface::class, 'device_view.storage')

        ->set('device_view.resolver.step.remembered', RememberedStep::class)
            ->args([
                new Reference('device_view.storage'),
                new Reference('device_view.view_names'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])

        ->set('device_view.remember_policy', RememberPolicy::class)
            ->args([
                new AbstractArgument('remember.on_switch'),
                new AbstractArgument('remember.detected_view'),
                new AbstractArgument('remember.lifetime'),
                new AbstractArgument('remember.consented_lifetime'),
                new AbstractArgument('whether consent.provider is set'),
            ])

        ->set('device_view.remember_listener', RememberListener::class)
            ->args([
                new Reference('device_view.resolver'),
                new Reference('device_view.storage'),
                new Reference('device_view.remember_policy'),
                new Reference('device_view.view_names'),
                new AbstractArgument('http_cache.private_when_remembering'),
                new AbstractArgument('tablet_as_mobile'),
                // set only when consent.provider is
                new Reference('device_view.consent_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('device_view.route_defaults'),
                new Reference('event_dispatcher'),
            ])
            ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse', 'priority' => 0])
    ;
};
