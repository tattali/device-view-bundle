<?php

declare(strict_types=1);

use DeviceViewBundle\EventListener\VaryListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;

// The HTTP cache headers of the main response. The Vary listener runs at priority 0 before the
// remember listener (remember.php), as DeviceViewBundle::loadExtension() imports this file first.
// It is removed when http_cache.vary is false. http_cache.private_when_remembering is an
// argument of the remember listener, which knows when the stored value is written or expired.
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.vary_listener', VaryListener::class)
            ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse', 'priority' => 0])
    ;
};
