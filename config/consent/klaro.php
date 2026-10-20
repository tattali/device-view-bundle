<?php

declare(strict_types=1);

use DeviceViewBundle\Consent\KlaroConsentChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The Klaro reader: consent.provider: klaro (see DeviceViewBundle::builtInConsentReader()).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.consent.klaro', KlaroConsentChecker::class)
    ;
};
