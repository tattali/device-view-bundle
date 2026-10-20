<?php

declare(strict_types=1);

use DeviceViewBundle\Consent\ComplianzConsentChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The Complianz reader: consent.provider: complianz (see DeviceViewBundle::builtInConsentReader()).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.consent.complianz', ComplianzConsentChecker::class)
    ;
};
