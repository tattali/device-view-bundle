<?php

declare(strict_types=1);

use DeviceViewBundle\Consent\CookieConsentConsentChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// The CookieConsent v3 reader: consent.provider: cookieconsent (see DeviceViewBundle::builtInConsentReader()).
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('device_view.consent.cookieconsent', CookieConsentConsentChecker::class)
    ;
};
