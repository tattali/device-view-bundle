<?php

declare(strict_types=1);

use DeviceViewBundle\Tests\App\TestController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('home', '/')->controller([TestController::class, 'home']);
    $routes->add('view', '/view')->controller([TestController::class, 'view']);
    $routes->add('blog', '/blog/{slug}')->controller([TestController::class, 'view']);
    $routes->add('form', '/form')->controller([TestController::class, 'view'])->methods(['GET', 'HEAD', 'POST']);
    $routes->add('cacheable', '/cacheable')->controller([TestController::class, 'cacheable']);
    $routes->add('set_view', '/set-view/{view}')->controller([TestController::class, 'setView']);
    $routes->add('set_remember_off', '/set-remember-off')->controller([TestController::class, 'setRememberOff']);
    $routes->add('set_consent', '/set-consent/{value}')->controller([TestController::class, 'setConsent']);
    $routes->add('readers', '/readers/{name}')->controller([TestController::class, 'readers']);
    $routes->add('sub_request', '/with-sub-request')->controller([TestController::class, 'subRequest']);
    $routes->add('no_redirect', '/no-redirect')->controller([TestController::class, 'view'])->defaults(['_device_view_redirect' => 'none']);
    $routes->add('without_path', '/without-path')->controller([TestController::class, 'view'])->defaults(['_device_view_redirect' => 'redirect_without_path']);
    $routes->add('always_redirect', '/always-redirect')->controller([TestController::class, 'view'])->defaults(['_device_view_redirect' => 'redirect']);
    $routes->add('bad_default', '/bad-default')->controller([TestController::class, 'view'])->defaults(['_device_view_redirect' => 'sometimes']);
    $routes->add('no_remember', '/no-remember')->controller([TestController::class, 'view'])->defaults(['_device_view_remember' => false]);
    // a string, as XML routes give
    $routes->add('no_remember_xml', '/no-remember-xml')->controller([TestController::class, 'view'])->defaults(['_device_view_remember' => 'false']);
    $routes->add('remember', '/remember')->controller([TestController::class, 'view'])->defaults(['_device_view_remember' => true]);
    $routes->add('custom_keys', '/custom-keys')->controller([TestController::class, 'view'])->defaults(['_dv_redirect' => 'none', '_dv_remember' => false]);
};
