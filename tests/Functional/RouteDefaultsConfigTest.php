<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The configuration of the per-route overrides: route_defaults.redirect and
 * route_defaults.remember, the names of the route defaults the bundle reads.
 */
final class RouteDefaultsConfigTest extends TestCase
{
    use HandlesRequests;

    public function testTheDefaultKeys(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com']]]);

        self::assertSame('mobile detector', $this->handle(self::request('http://www.example.com/no-redirect', 'iphone'))->getContent());
        self::assertSame([], $this->handle(self::request('/no-remember?device_view=mobile'))->headers->getCookies());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keys(): iterable
    {
        yield 'redirect' => ['redirect'];
        yield 'remember' => ['remember'];
    }

    #[Group('CFG-59')]
    #[DataProvider('keys')]
    public function testAKeyCannotBeEmpty(string $key): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('The path "device_view.route_defaults.%s" cannot contain an empty value', $key));

        $this->boot(['route_defaults' => [$key => '']]);
    }

    #[Group('CFG-59')]
    #[DataProvider('keys')]
    public function testAKeyIsAString(string $key): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('Invalid type for path "device_view.route_defaults.%s"', $key));

        $this->boot(['route_defaults' => [$key => false]]);
    }

    #[Group('CFG-60')]
    public function testTheKeysDiffer(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.route_defaults.redirect and device_view.route_defaults.remember must be different route defaults, both are "_dv".');

        $this->boot(['route_defaults' => ['redirect' => '_dv', 'remember' => '_dv']]);
    }

    #[Group('CFG-60')]
    public function testAKeyCannotBeTheOtherDefault(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('both are "_device_view_remember".');

        $this->boot(['route_defaults' => ['redirect' => '_device_view_remember']]);
    }

    #[Group('CFG-61')]
    #[DataProvider('keys')]
    public function testAKeyCannotBeTheContextAttribute(string $key): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('device_view.route_defaults.%s cannot be "_device_view", the request attribute that holds the device view context.', $key));

        $this->boot(['route_defaults' => [$key => '_device_view']]);
    }

    public function testAnyOtherKeyIsAccepted(): void
    {
        $this->boot(['route_defaults' => ['redirect' => 'device_view_redirect', 'remember' => '_device_view_x']]);

        self::assertSame(200, $this->handle(self::request('/view'))->getStatusCode());
    }
}
