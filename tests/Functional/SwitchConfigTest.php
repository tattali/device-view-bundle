<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The configuration of the switch: switch.* and the redirect keys it shares with the device
 * redirects, redirect.methods and redirect.cache_control.
 */
final class SwitchConfigTest extends TestCase
{
    use HandlesRequests;

    public function testTheDefaults(): void
    {
        $this->boot([]);

        $response = $this->handle(self::request('/view?device_view=tablet'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(302, $this->handle(self::request('/form?device_view=tablet', method: 'HEAD'))->getStatusCode());
        self::assertSame(200, $this->handle(self::request('/form?device_view=tablet', method: 'POST'))->getStatusCode());
    }

    #[Group('CFG-17')]
    public function testSwitchTargetIsAnEnum(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "back" is not allowed for path "device_view.switch.target"');

        $this->boot(['switch' => ['target' => 'back']]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidStatuses(): iterable
    {
        yield '301' => [301, '301'];
        yield '308' => [308, '308'];
        yield '200' => [200, '200'];
        yield 'string' => ['302', '"302"'];
    }

    #[Group('CFG-18')]
    #[DataProvider('invalidStatuses')]
    public function testSwitchStatusIsOneOf302303And307(mixed $status, string $shown): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value '.$shown.' is not allowed for path "device_view.switch.status"');

        $this->boot(['switch' => ['status' => $status]]);
    }

    #[Group('CFG-19')]
    public function testSwitchParameterIsNotEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "device_view.switch.parameter" cannot contain an empty value');

        $this->boot(['switch' => ['parameter' => '']]);
    }

    public function testSwitchEnabledIsABoolean(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.switch.enabled"');

        $this->boot(['switch' => ['enabled' => 'yes']]);
    }

    public function testUnknownSwitchKey(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "param" under "device_view.switch"');

        $this->boot(['switch' => ['param' => 'view']]);
    }

    #[Group('CFG-42')]
    public function testRedirectMethodsAreNotEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.redirect.methods must list at least one method.');

        $this->boot(['redirect' => ['methods' => []]]);
    }

    public function testARedirectMethodIsNotEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "device_view.redirect.methods.0" cannot contain an empty value');

        $this->boot(['redirect' => ['methods' => ['']]]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidCacheControls(): iterable
    {
        yield 'empty string' => ['', '""'];
        yield 'integer' => [3600, '3600'];
        yield 'boolean' => [false, 'false'];
    }

    #[DataProvider('invalidCacheControls')]
    public function testRedirectCacheControlIsNullOrAString(mixed $value, string $shown): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.redirect.cache_control must be null or a non-empty string, got '.$shown.'.');

        $this->boot(['redirect' => ['cache_control' => $value]]);
    }
}
