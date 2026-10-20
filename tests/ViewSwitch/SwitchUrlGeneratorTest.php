<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\ViewSwitch;

use DeviceViewBundle\Redirect\RedirectHost;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SwitchUrlGeneratorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function currentPages(): iterable
    {
        yield 'only the switch parameter' => ['/view?device_view=mobile', '/view'];
        yield 'no query' => ['/view', '/view'];
        yield 'other parameters in order' => ['/view?b=2&device_view=mobile&a=1', '/view?b=2&a=1'];
        yield 'RFC 3986 encoding' => ['/view?q=a+b&tags[]=x&device_view=mobile', '/view?q=a%20b&tags%5B0%5D=x'];
        yield 'an array switch parameter' => ['/view?device_view[]=x&a=1', '/view?a=1'];
        yield 'home page' => ['/?device_view=mobile', '/'];
        yield 'encoded path' => ['/caf%C3%A9?device_view=mobile', '/caf%C3%A9'];
    }

    #[DataProvider('currentPages')]
    public function testTheRedirectToTheCurrentPageDropsTheSwitchParameter(string $uri, string $expected): void
    {
        self::assertSame($expected, new SwitchUrlGenerator()->redirectUrl(Request::create('http://localhost'.$uri), SwitchTarget::Current));
    }

    public function testTheRedirectKeepsTheBaseUrl(): void
    {
        $generator = new SwitchUrlGenerator();
        $request = self::appRequest('/app/view?a=1&device_view=mobile');

        self::assertSame('/app/view?a=1', $generator->redirectUrl($request, SwitchTarget::Current));
        self::assertSame('/app/', $generator->redirectUrl($request, SwitchTarget::Home));
    }

    public function testTheRedirectToTheHomePageDropsTheWholeQuery(): void
    {
        self::assertSame('/', new SwitchUrlGenerator()->redirectUrl(Request::create('/blog/post?a=1&device_view=mobile'), SwitchTarget::Home));
    }

    public function testTheRedirectUsesTheConfiguredParameter(): void
    {
        self::assertSame('/view?device_view=x', new SwitchUrlGenerator('view')->redirectUrl(Request::create('/view?view=tablet&device_view=x'), SwitchTarget::Current));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function pathsOfOtherHosts(): iterable
    {
        yield 'two slashes' => ['http://localhost//evil.example/view?a=1&device_view=mobile', '/evil.example/view?a=1', '/evil.example/view?a=1&device_view=tablet'];
        yield 'three slashes' => ['http://localhost///evil.example/view', '/evil.example/view', '/evil.example/view?device_view=tablet'];
    }

    #[DataProvider('pathsOfOtherHosts')]
    public function testARelativeUrlNeverNamesAnotherHost(string $uri, string $redirectUrl, string $switchUrl): void
    {
        $generator = new SwitchUrlGenerator();
        $request = Request::create($uri);

        self::assertSame($redirectUrl, $generator->redirectUrl($request, SwitchTarget::Current));
        self::assertSame($switchUrl, $generator->switchUrl($request, SwitchTarget::Current, 'tablet'));
    }

    public function testABackslashAfterTheLeadingSlashIsDropped(): void
    {
        // Request::create() refuses a backslash, but a server can pass one in REQUEST_URI
        $request = new Request(server: ['REQUEST_URI' => '/\\evil.example/view?device_view=mobile', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/srv/index.php']);
        $generator = new SwitchUrlGenerator();

        self::assertSame('/\\evil.example/view', $request->getPathInfo());
        self::assertSame('/evil.example/view', $generator->redirectUrl($request, SwitchTarget::Current));
        self::assertSame('/evil.example/view?device_view=tablet', $generator->switchUrl($request, SwitchTarget::Current, 'tablet'));
    }

    public function testABaseUrlKeepsTheSlashesThatFollowIt(): void
    {
        self::assertSame('/app//x', new SwitchUrlGenerator()->redirectUrl(self::appRequest('/app//x?device_view=mobile'), SwitchTarget::Current));
    }

    public function testTheSwitchUrlOfTheCurrentPageSetsTheParameterLast(): void
    {
        $generator = new SwitchUrlGenerator();

        self::assertSame('/view?a=1&device_view=mobile', $generator->switchUrl(Request::create('/view?a=1'), SwitchTarget::Current, 'mobile'));
        self::assertSame('/view?a=1&b=2&device_view=desktop', $generator->switchUrl(Request::create('/view?a=1&device_view=mobile&b=2'), SwitchTarget::Current, 'desktop'));
        self::assertSame('/view?q=a%20b&device_view=m', $generator->switchUrl(Request::create('/view?q=a+b'), SwitchTarget::Current, 'm'));
        self::assertSame('/app/view?device_view=tablet', $generator->switchUrl(self::appRequest('/app/view'), SwitchTarget::Current, 'tablet'));
    }

    public function testTheSwitchUrlOfTheHomePageHasOnlyTheParameter(): void
    {
        $generator = new SwitchUrlGenerator('view');

        self::assertSame('/?view=tablet', $generator->switchUrl(Request::create('/blog/post?a=1'), SwitchTarget::Home, 'tablet'));
        self::assertSame('/app/?view=tablet', $generator->switchUrl(self::appRequest('/app/blog/post?a=1'), SwitchTarget::Home, 'tablet'));
    }

    public function testWithoutARequestTheSwitchUrlIsTheHomePage(): void
    {
        $generator = new SwitchUrlGenerator();

        self::assertSame('/?device_view=mobile', $generator->switchUrl(null, SwitchTarget::Current, 'mobile'));
        self::assertSame('/?device_view=desktop', $generator->switchUrl(null, SwitchTarget::Home, 'desktop'));
    }

    public function testTheHostUrlKeepsThePathAndTheQueryByDefault(): void
    {
        $host = RedirectHost::parse('https://m.example.com/shop');
        self::assertNotNull($host);
        $request = self::appRequest('/app/blog/post?q=a+b&device_view=x&a=1');

        self::assertSame('https://m.example.com/shop/blog/post?q=a%20b&a=1&device_view=m', new SwitchUrlGenerator()->hostUrl($request, $host, 'm'));
    }

    public function testTheHostUrlWithoutQueryOrPath(): void
    {
        $host = RedirectHost::parse('https://m.example.com');
        self::assertNotNull($host);
        $generator = new SwitchUrlGenerator('view');
        $request = Request::create('http://www.example.com/blog/post?a=1&view=x');

        self::assertSame('https://m.example.com/blog/post?view=mobile', $generator->hostUrl($request, $host, 'mobile', keepQuery: false));
        self::assertSame('https://m.example.com/?view=mobile', $generator->hostUrl($request, $host, 'mobile', false));
        self::assertSame('https://m.example.com/?view=mobile', $generator->hostUrl($request, $host, 'mobile', false, false));
    }

    private static function appRequest(string $uri): Request
    {
        return Request::create('http://localhost'.$uri, server: ['SCRIPT_NAME' => '/app/index.php', 'SCRIPT_FILENAME' => '/srv/app/index.php']);
    }
}
