<?php

declare(strict_types=1);

use DeviceViewBundle\Consent\CookiebotConsentChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The Cookiebot reader: consent.provider: cookiebot (see DeviceViewBundle::builtInConsentReader()).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.consent.cookiebot', CookiebotConsentChecker::class)
    ;
};
