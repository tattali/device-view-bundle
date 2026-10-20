<?php

declare(strict_types=1);

use DeviceViewBundle\Consent\DidomiConsentChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The Didomi reader: consent.provider: didomi (see DeviceViewBundle::builtInConsentReader()).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.consent.didomi', DidomiConsentChecker::class)
    ;
};
