<?php

declare(strict_types=1);

use DeviceViewBundle\Detector\DeviceDetectorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The bundle's services. Each feature adds its own file next to this one.
return static function (ContainerConfigurator $container): void {
    // device_view.detector is set by DeviceViewBundle::loadExtension() from detector.id
    $container->services()
        ->alias(DeviceDetectorInterface::class, 'device_view.detector')
    ;
};
