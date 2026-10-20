<?php

declare(strict_types=1);

use DeviceViewBundle\Consent\OneTrustConsentChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The OneTrust reader: consent.provider: onetrust (see DeviceViewBundle::builtInConsentReader()).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.consent.onetrust', OneTrustConsentChecker::class)
    ;
};
