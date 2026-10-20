<?php

declare(strict_types=1);

use DeviceViewBundle\Consent\AxeptioConsentChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The Axeptio reader: consent.provider: axeptio (see DeviceViewBundle::builtInConsentReader()).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.consent.axeptio', AxeptioConsentChecker::class)
    ;
};
