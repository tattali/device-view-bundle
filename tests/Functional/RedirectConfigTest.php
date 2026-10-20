<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The configuration of the device redirects: redirect.<mobile|tablet|desktop>.
 */
final class RedirectConfigTest extends TestCase
{
    use HandlesRequests;

    private const string HOST_MESSAGE = 'device_view.redirect.mobile.host "%s" must be an absolute http(s) URL with a host, an optional port and an optional path, and no query, fragment or user info.';

    public function testRedirectsAreDisabledByDefault(): void
    {
        $this->boot([]);

        $response = $this->handle(self::request('http://www.example.com/view', 'iphone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
    }

    public function testADisabledRedirectNeedsNoHost(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => false, 'host' => 'not a url']]]);

        self::assertSame('mobile detector', $this->handle(self::request('http://www.example.com/view', 'iphone'))->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function views(): iterable
    {
        yield 'mobile' => ['mobile'];
        yield 'tablet' => ['tablet'];
        yield 'desktop' => ['desktop'];
    }

    #[Group('CFG-33')]
    #[DataProvider('views')]
    public function testTheHostIsRequired(string $view): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('device_view.redirect.%1$s.host is required when the %1$s redirect is enabled.', $view));

        $this->boot(['redirect' => [$view => ['enabled' => true]]]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHosts(): iterable
    {
        yield 'CFG-34 not absolute' => ['m.example.com'];
        yield 'CFG-35 query' => ['https://m.example.com/?a=1'];
        yield 'empty query' => ['https://m.example.com?'];
        yield 'CFG-36 fragment' => ['https://m.example.com/#top'];
        yield 'empty fragment' => ['https://m.example.com#'];
        yield 'CFG-37 user info' => ['https://user:pass@m.example.com'];
        yield 'user only' => ['https://user@m.example.com'];
        yield 'CFG-38 scheme' => ['ftp://m.example.com'];
        yield 'no host' => ['https://'];
        yield 'one slash' => ['https:/m.example.com'];
        yield 'relative' => ['/shop'];
        yield 'empty' => [''];
        yield 'space' => ['https://m.example.com/a b'];
        yield 'new line' => ["https://m.example.com/\nX-Evil: 1"];
        yield 'dots only' => ['https://./'];
    }

    #[Group('CFG-34')]
    #[Group('CFG-35')]
    #[Group('CFG-36')]
    #[Group('CFG-37')]
    #[Group('CFG-38')]
    #[Group('REG-16')]
    #[DataProvider('invalidHosts')]
    public function testTheHostMustBeAnAbsoluteHttpUrl(string $host): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf(self::HOST_MESSAGE, $host));

        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => $host]]]);
    }

    public function testTheHostOfEachViewIsChecked(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.redirect.desktop.host "www.example.com" must be an absolute http(s) URL');

        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com'], 'desktop' => ['enabled' => true, 'host' => 'www.example.com']]]);
    }

    public function testTheHostIsAString(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.redirect.mobile.host must be null or a string, got 3.');

        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 3]]]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validHosts(): iterable
    {
        yield 'http' => ['http://m.example.com'];
        yield 'upper-case scheme' => ['HTTPS://m.example.com'];
        yield 'port' => ['https://m.example.com:8443'];
        yield 'base path' => ['https://m.example.com/shop/'];
        yield 'IPv4' => ['http://192.0.2.1:8080'];
        yield 'IPv6' => ['http://[2001:db8::1]:8080'];
    }

    #[DataProvider('validHosts')]
    public function testValidHosts(string $host): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => $host]]]);

        self::assertSame(302, $this->handle(self::request('http://www.example.com/', 'iphone'))->getStatusCode());
    }

    #[Group('CFG-39')]
    public function testAHostFromAnEnvVarIsCheckedAtRuntimeOnly(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => '%env(DV_MOBILE_HOST)%']]]);

        self::assertNotNull($this->kernel);
    }

    public function testAHostBuiltFromAnEnvVarIsCheckedAtRuntimeOnly(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://%env(DV_MOBILE_HOST)%']]]);

        self::assertNotNull($this->kernel);
    }

    #[Group('CFG-40')]
    public function testTheActionIsAnEnum(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "back" is not allowed for path "device_view.redirect.mobile.action"');

        $this->boot(['redirect' => ['mobile' => ['action' => 'back']]]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidStatuses(): iterable
    {
        yield '304' => [304, '304'];
        yield '200' => [200, '200'];
        yield 'string' => ['302', '"302"'];
    }

    #[Group('CFG-41')]
    #[DataProvider('invalidStatuses')]
    public function testTheStatusIsARedirectStatus(mixed $status, string $shown): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value '.$shown.' is not allowed for path "device_view.redirect.mobile.status"');

        $this->boot(['redirect' => ['mobile' => ['status' => $status]]]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function validStatuses(): iterable
    {
        foreach ([301, 302, 303, 307, 308] as $status) {
            yield (string) $status => [$status];
        }
    }

    #[DataProvider('validStatuses')]
    public function testValidStatuses(int $status): void
    {
        $this->boot(['redirect' => ['tablet' => ['enabled' => true, 'host' => 'https://t.example.com', 'status' => $status]]]);

        self::assertSame($status, $this->handle(self::request('http://www.example.com/', 'ipad_legacy'))->getStatusCode());
    }

    public function testEnabledIsABoolean(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.redirect.mobile.enabled"');

        $this->boot(['redirect' => ['mobile' => ['enabled' => 'yes']]]);
    }

    public function testKeepQueryIsABoolean(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.redirect.desktop.keep_query"');

        $this->boot(['redirect' => ['desktop' => ['keep_query' => 'yes']]]);
    }

    public function testUnknownRedirectKey(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "path" under "device_view.redirect.mobile"');

        $this->boot(['redirect' => ['mobile' => ['path' => '/m']]]);
    }

    public function testUnknownView(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "phone" under "device_view.redirect"');

        $this->boot(['redirect' => ['phone' => ['enabled' => true]]]);
    }
}
