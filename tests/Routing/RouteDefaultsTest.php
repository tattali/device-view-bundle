<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Routing;

use DeviceViewBundle\Redirect\RedirectAction;
use DeviceViewBundle\Routing\RouteDefaults;
use DeviceViewBundle\Tests\App\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

final class RouteDefaultsTest extends TestCase
{
    public function testNoDefaultsGiveNoOverride(): void
    {
        $logger = new TestLogger();
        $defaults = new RouteDefaults(logger: $logger);

        self::assertNull($defaults->redirect(new Request()));
        self::assertNull($defaults->remember(new Request()));
        self::assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{string, RedirectAction}>
     */
    public static function redirects(): iterable
    {
        yield 'redirect' => ['redirect', RedirectAction::Redirect];
        yield 'redirect_without_path' => ['redirect_without_path', RedirectAction::RedirectWithoutPath];
        yield 'none' => ['none', RedirectAction::None];
    }

    #[DataProvider('redirects')]
    public function testTheRedirectDefault(string $value, RedirectAction $action): void
    {
        $logger = new TestLogger();

        self::assertSame($action, new RouteDefaults(logger: $logger)->redirect(self::requestWith(['_device_view_redirect' => $value])));
        self::assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidRedirects(): iterable
    {
        yield 'unknown word' => ['sometimes', '"sometimes"'];
        yield 'upper case' => ['NONE', '"NONE"'];
        yield 'padded' => [' none', '" none"'];
        yield 'empty' => ['', '""'];
        yield 'boolean' => [false, 'of type bool'];
        yield 'array' => [['none'], 'of type array'];
        yield 'enum' => [RedirectAction::None, 'of type DeviceViewBundle\Redirect\RedirectAction'];
    }

    #[DataProvider('invalidRedirects')]
    public function testAnInvalidRedirectDefaultIsIgnoredAndLogged(mixed $value, string $shown): void
    {
        $logger = new TestLogger();

        self::assertNull(new RouteDefaults(logger: $logger)->redirect(self::requestWith(['_device_view_redirect' => $value])));
        self::assertSame([['level' => LogLevel::DEBUG, 'message' => 'Ignored the invalid route default _device_view_redirect '.$shown.'.']], $logger->records);
    }

    public function testANullRedirectDefaultIsNoOverride(): void
    {
        $logger = new TestLogger();

        self::assertNull(new RouteDefaults(logger: $logger)->redirect(self::requestWith(['_device_view_redirect' => null])));
        self::assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function remembers(): iterable
    {
        yield 'true' => [true, true];
        yield 'false' => [false, false];
        yield '"true"' => ['true', true];
        yield '"false"' => ['false', false];
        yield '"1"' => ['1', true];
        yield '"0"' => ['0', false];
    }

    #[DataProvider('remembers')]
    public function testTheRememberDefault(mixed $value, bool $remember): void
    {
        $logger = new TestLogger();

        self::assertSame($remember, new RouteDefaults(logger: $logger)->remember(self::requestWith(['_device_view_remember' => $value])));
        self::assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidRemembers(): iterable
    {
        yield 'other word' => ['yes', '"yes"'];
        yield 'upper case' => ['FALSE', '"FALSE"'];
        yield 'padded' => ['false ', '"false "'];
        yield 'empty' => ['', '""'];
        yield 'integer' => [0, 'of type int'];
        yield 'array' => [[false], 'of type array'];
    }

    #[DataProvider('invalidRemembers')]
    public function testAnInvalidRememberDefaultIsIgnoredAndLogged(mixed $value, string $shown): void
    {
        $logger = new TestLogger();

        self::assertNull(new RouteDefaults(logger: $logger)->remember(self::requestWith(['_device_view_remember' => $value])));
        self::assertSame([['level' => LogLevel::DEBUG, 'message' => 'Ignored the invalid route default _device_view_remember '.$shown.'.']], $logger->records);
    }

    public function testCustomKeys(): void
    {
        $logger = new TestLogger();
        $defaults = new RouteDefaults('_dv_redirect', '_dv_remember', $logger);
        $request = self::requestWith([
            '_device_view_redirect' => 'none',
            '_device_view_remember' => false,
            '_dv_redirect' => 'redirect_without_path',
            '_dv_remember' => 'bad',
        ]);

        self::assertSame(RedirectAction::RedirectWithoutPath, $defaults->redirect($request));
        self::assertNull($defaults->remember($request));
        self::assertSame(['Ignored the invalid route default _dv_remember "bad".'], $logger->messages(LogLevel::DEBUG));
    }

    public function testTheDefaultKeysAreNotReadWithCustomKeys(): void
    {
        $defaults = new RouteDefaults('_dv_redirect', '_dv_remember');
        $request = self::requestWith(['_device_view_redirect' => 'none', '_device_view_remember' => false]);

        self::assertNull($defaults->redirect($request));
        self::assertNull($defaults->remember($request));
    }

    public function testItWorksWithoutALogger(): void
    {
        $request = self::requestWith(['_device_view_redirect' => 'bad', '_device_view_remember' => 'bad']);

        self::assertNull(new RouteDefaults()->redirect($request));
        self::assertNull(new RouteDefaults()->remember($request));
    }

    public function testAQueryParameterIsNotARouteDefault(): void
    {
        $defaults = new RouteDefaults();
        $request = Request::create('/view?_device_view_redirect=none&_device_view_remember=0');

        self::assertNull($defaults->redirect($request));
        self::assertNull($defaults->remember($request));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function requestWith(array $attributes): Request
    {
        $request = new Request();
        $request->attributes->add($attributes);

        return $request;
    }
}
