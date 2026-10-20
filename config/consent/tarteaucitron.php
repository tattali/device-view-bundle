<?php

declare(strict_types=1);

use DeviceViewBundle\Consent\TarteaucitronConsentChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The tarteaucitron reader: consent.provider: tarteaucitron (see DeviceViewBundle::consentChecker()).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.consent.tarteaucitron', TarteaucitronConsentChecker::class)
    ;
};
