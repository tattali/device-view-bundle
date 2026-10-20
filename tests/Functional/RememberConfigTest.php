<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Remember\CookieStorage;
use DeviceViewBundle\Remember\SessionStorage;
use DeviceViewBundle\Tests\App\Remember\ArrayStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The configuration of the remember storage: remember.* and http_cache.private_when_remembering.
 */
final class RememberConfigTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    public function testTheStorageIsTheCookieStorage(): void
    {
        $this->boot([]);

        self::assertInstanceOf(CookieStorage::class, $this->service('test.device_view.storage'));
        self::assertSame($this->service('test.device_view.storage'), $this->service('test.view_storage_interface'));
    }

    #[Group('CFG-21')]
    public function testLifetimeIsNotNegative(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value -1 is too small for path "device_view.remember.lifetime"');

        $this->boot(['remember' => ['lifetime' => -1]]);
    }

    public function testConsentedLifetimeIsNotNegative(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value -1 is too small for path "device_view.remember.consented_lifetime"');

        $this->boot(['remember' => ['consented_lifetime' => -1]]);
    }

    public function testLifetimeIsAnInteger(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.remember.lifetime"');

        $this->boot(['remember' => ['lifetime' => '3600']]);
    }

    #[Group('CFG-22')]
    public function testConsentedLifetimeIsNotBelowLifetime(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.remember.consented_lifetime (60) must be greater than or equal to device_view.remember.lifetime (3600).');

        $this->boot(['remember' => ['lifetime' => 3600, 'consented_lifetime' => 60]]);
    }

    public function testConsentedLifetimeMayEqualLifetime(): void
    {
        $this->boot(['remember' => ['lifetime' => 3600, 'consented_lifetime' => 3600]]);

        self::assertSetCookie(self::sc('mobile', 3600), $this->handle(self::request('/view?device_view=mobile')));
    }

    public function testTheDefaultConsentedLifetimeIsNotBelowAPersistentLifetime(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.remember.consented_lifetime (31536000) must be greater than or equal to device_view.remember.lifetime (31536001).');

        $this->boot(['remember' => ['lifetime' => 31536001]]);
    }

    #[Group('CFG-23')]
    public function testConsentedLifetimeIsFreeWhenLifetimeIsZero(): void
    {
        $this->boot(['remember' => ['lifetime' => 0, 'consented_lifetime' => 60]]);

        self::assertSetCookie(self::sc('mobile'), $this->handle(self::request('/view?device_view=mobile')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function booleanKeys(): iterable
    {
        yield 'on_switch' => ['on_switch'];
        yield 'detected_view' => ['detected_view'];
    }

    #[DataProvider('booleanKeys')]
    public function testRememberFlagsAreBooleans(string $key): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('Invalid type for path "device_view.remember.%s"', $key));

        $this->boot(['remember' => [$key => 'yes']]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function booleanCookieKeys(): iterable
    {
        yield 'http_only' => ['http_only'];
        yield 'raw' => ['raw'];
    }

    #[DataProvider('booleanCookieKeys')]
    public function testCookieFlagsAreBooleans(string $key): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('Invalid type for path "device_view.remember.cookie.%s"', $key));

        $this->boot(['remember' => ['cookie' => [$key => 'yes']]]);
    }

    public function testPrivateWhenRememberingIsABoolean(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.http_cache.private_when_remembering"');

        $this->boot(['http_cache' => ['private_when_remembering' => 'yes']]);
    }

    #[Group('CFG-24')]
    public function testCookieSecureIsAutoTrueOrFalse(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/device_view\.remember\.cookie\.secure.*auto/');

        $this->boot(['remember' => ['cookie' => ['secure' => 'maybe']]]);
    }

    #[Group('CFG-25')]
    public function testCookieSameSiteIsAnEnum(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "relaxed" is not allowed for path "device_view.remember.cookie.same_site"');

        $this->boot(['remember' => ['cookie' => ['same_site' => 'relaxed']]]);
    }

    #[Group('CFG-26')]
    public function testSameSiteNoneNeedsSecure(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.remember.cookie.same_site "none" needs device_view.remember.cookie.secure set to true or auto.');

        $this->boot(['remember' => ['cookie' => ['same_site' => 'none', 'secure' => false]]]);
    }

    public function testSecureFalseIsFineWithAnotherSameSite(): void
    {
        $this->boot(['remember' => ['cookie' => ['same_site' => 'strict', 'secure' => false]]]);

        self::assertSetCookie('device_view=mobile; path=/; httponly; samesite=strict', $this->handle(self::request('https://localhost/view?device_view=mobile')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCookieNames(): iterable
    {
        yield 'semicolon' => ['dv;x'];
        yield 'equals' => ['dv=x'];
        yield 'comma' => ['dv,x'];
        yield 'space' => ['dv x'];
        yield 'dot, which PHP reads as an underscore' => ['dv.x'];
        yield 'bracket, which PHP reads as an array' => ['dv[x]'];
        yield 'empty' => [''];
        yield 'line feed' => ["dv\nx"];
        yield 'non-ASCII' => ['dvé'];
    }

    #[Group('CFG-27')]
    #[DataProvider('invalidCookieNames')]
    public function testCookieNameIsAToken(string $name): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('The cookie name "%s" is invalid.', $name));

        $this->boot(['remember' => ['cookie' => ['name' => $name]]]);
    }

    public function testCookieNameMayUseTokenCharacters(): void
    {
        $name = "Dv-1_!#$%&'*+^`|~";
        $this->boot(['remember' => ['cookie' => ['name' => $name]]]);

        $cookies = $this->handle(self::request('/view?device_view=mobile'))->headers->getCookies();

        self::assertCount(1, $cookies);
        self::assertSame($name, $cookies[0]->getName());
    }

    public function testCookiePathIsNotEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "device_view.remember.cookie.path" cannot contain an empty value');

        $this->boot(['remember' => ['cookie' => ['path' => '']]]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidDomains(): iterable
    {
        yield 'empty string' => ['', '""'];
        yield 'integer' => [1, '1'];
    }

    #[DataProvider('invalidDomains')]
    public function testCookieDomainIsNullOrAString(mixed $domain, string $shown): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.remember.cookie.domain must be null or a non-empty string, got '.$shown.'.');

        $this->boot(['remember' => ['cookie' => ['domain' => $domain]]]);
    }

    #[Group('CFG-20')]
    public function testAStorageServiceImplementsTheInterface(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.not_a_storage" set in device_view.remember.storage must implement DeviceViewBundle\Remember\ViewStorageInterface.');

        $this->boot(['remember' => ['storage' => 'app.not_a_storage']], ['app.not_a_storage' => \stdClass::class]);
    }

    #[Group('CFG-56')]
    public function testAStorageServiceExists(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.nope" set in device_view.remember.storage does not exist.');

        $this->boot(['remember' => ['storage' => 'app.nope']]);
    }

    #[Group('CFG-57')]
    public function testSessionStorageNeedsTheFrameworkSession(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.remember.storage "session" needs the framework session: enable framework.session, or set device_view.remember.storage to another storage.');

        $this->boot(['remember' => ['storage' => 'session']]);
    }

    public function testSessionStorageBuildsWithTheFrameworkSession(): void
    {
        $this->boot(['remember' => ['storage' => 'session']], framework: ['session' => true]);

        self::assertInstanceOf(SessionStorage::class, $this->service('test.device_view.storage'));
    }

    public function testOtherStoragesNeedNoFrameworkSession(): void
    {
        $this->boot(['remember' => ['storage' => 'none']]);
        $this->boot(['remember' => ['storage' => 'app.array_storage']], ['app.array_storage' => ArrayStorage::class]);

        self::assertInstanceOf(ArrayStorage::class, $this->service('test.device_view.storage'));
    }

    #[Group('CFG-58')]
    public function testAStorageServiceCannotBeTheStorageAlias(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.remember.storage cannot be "device_view.storage", the alias it sets: set it to the id of your own service.');

        $this->boot(['remember' => ['storage' => 'device_view.storage']]);
    }

    public function testStorageIsNotEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "device_view.remember.storage" cannot contain an empty value');

        $this->boot(['remember' => ['storage' => '']]);
    }

    public function testStorageIsAString(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.remember.storage"');

        $this->boot(['remember' => ['storage' => ['cookie']]]);
    }

    #[Group('CFG-28')]
    public function testSessionKeyIsNotEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "device_view.remember.session.key" cannot contain an empty value');

        $this->boot(['remember' => ['session' => ['key' => '']]]);
    }

    #[Group('CFG-29')]
    public function testStorageNoneCannotRememberADetectedView(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.remember.detected_view cannot be true when device_view.remember.storage is "none".');

        $this->boot(['remember' => ['storage' => 'none', 'detected_view' => true]]);
    }

    public function testOnlyStorageNoneForbidsRememberingADetectedView(): void
    {
        $this->boot(['remember' => ['storage' => 'session', 'detected_view' => true]], framework: ['session' => true]);
        $this->boot(['remember' => ['storage' => 'none', 'detected_view' => false]]);

        self::assertSame('home page', $this->handle(self::request('/'))->getContent());
    }

    public function testUnknownRememberKey(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "duration" under "device_view.remember"');

        $this->boot(['remember' => ['duration' => 60]]);
    }
}
