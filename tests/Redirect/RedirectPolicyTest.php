<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Redirect;

use DeviceViewBundle\Redirect\RedirectAction;
use DeviceViewBundle\Redirect\RedirectPolicy;
use DeviceViewBundle\Tests\App\TestLogger;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

final class RedirectPolicyTest extends TestCase
{
    public function testWithoutRedirectsNothingRedirects(): void
    {
        $policy = new RedirectPolicy([], new ViewNames(), new SwitchUrlGenerator());

        self::assertFalse($policy->hasRedirects());
        self::assertNull($policy->deviceRedirect(Request::create('http://www.example.com/'), DeviceType::Mobile));
        self::assertNull($policy->switchUrl(Request::create('http://www.example.com/'), DeviceType::Mobile, SwitchTarget::Current));
    }

    public function testADeviceRedirectHasTheLocationAndStatusOfItsView(): void
    {
        $policy = self::policy(['mobile' => self::options('https://m.example.com', status: 308)]);
        $request = Request::create('http://www.example.com/blog/post?a=1&device_view=x');

        self::assertTrue($policy->hasRedirects());
        self::assertSame(['https://m.example.com/blog/post?a=1&device_view=mobile', 308], $policy->deviceRedirect($request, DeviceType::Mobile));
        self::assertNull($policy->deviceRedirect($request, DeviceType::Desktop), 'a view without a redirect stays');
    }

    public function testTheActionAndKeepQueryShapeTheLocation(): void
    {
        $request = Request::create('http://www.example.com/blog/post?a=1');

        self::assertSame(['https://m.example.com/?device_view=mobile', 302], self::policy(['mobile' => self::options('https://m.example.com', RedirectAction::RedirectWithoutPath)])->deviceRedirect($request, DeviceType::Mobile));
        self::assertSame(['https://m.example.com/blog/post?device_view=mobile', 302], self::policy(['mobile' => self::options('https://m.example.com', keepQuery: false)])->deviceRedirect($request, DeviceType::Mobile));
        self::assertNull(self::policy(['mobile' => self::options('https://m.example.com', RedirectAction::None)])->deviceRedirect($request, DeviceType::Mobile));
    }

    public function testTheCurrentHostIsNeverRedirected(): void
    {
        $policy = self::policy(['mobile' => self::options('https://m.example.com')]);

        self::assertNull($policy->deviceRedirect(Request::create('https://m.example.com/view'), DeviceType::Mobile));
        self::assertNull($policy->switchUrl(Request::create('https://m.example.com/view?device_view=mobile'), DeviceType::Mobile, SwitchTarget::Current));
    }

    public function testTheSwitchGoesWhereTheDeviceRedirectGoes(): void
    {
        $request = Request::create('http://www.example.com/blog/post?a=1&device_view=mobile');
        $policy = self::policy(['mobile' => self::options('https://m.example.com', status: 301)]);

        self::assertSame('https://m.example.com/blog/post?a=1&device_view=mobile', $policy->switchUrl($request, DeviceType::Mobile, SwitchTarget::Current));
        self::assertSame('https://m.example.com/?device_view=mobile', $policy->switchUrl($request, DeviceType::Mobile, SwitchTarget::Home));
        self::assertSame('https://m.example.com/?device_view=mobile', self::policy(['mobile' => self::options('https://m.example.com', RedirectAction::RedirectWithoutPath)])->switchUrl($request, DeviceType::Mobile, SwitchTarget::Current));
        self::assertSame('https://m.example.com/blog/post?device_view=mobile', self::policy(['mobile' => self::options('https://m.example.com', keepQuery: false)])->switchUrl($request, DeviceType::Mobile, SwitchTarget::Current));
        self::assertNull(self::policy(['mobile' => self::options('https://m.example.com', RedirectAction::None)])->switchUrl($request, DeviceType::Mobile, SwitchTarget::Current));
    }

    public function testTheLocationUsesTheConfiguredNames(): void
    {
        $policy = new RedirectPolicy(['tablet' => self::options('https://t.example.com')], new ViewNames(['tablet' => 't']), new SwitchUrlGenerator('view'));

        self::assertSame(['https://t.example.com/?view=t', 302], $policy->deviceRedirect(Request::create('http://www.example.com/'), DeviceType::Tablet));
    }

    public function testAnInvalidHostDisablesItsRedirectWithAnErrorLog(): void
    {
        $logger = new TestLogger();
        $policy = new RedirectPolicy(['desktop' => self::options('www.example.com')], new ViewNames(), new SwitchUrlGenerator(), 'device_view', $logger);
        $request = Request::create('https://m.example.com/');

        self::assertNull($policy->deviceRedirect($request, DeviceType::Desktop));
        self::assertNull($policy->switchUrl($request, DeviceType::Desktop, SwitchTarget::Current));
        self::assertSame(array_fill(0, 2, 'The redirect host "www.example.com" of the desktop view is invalid: expected an absolute http(s) URL without query, fragment or user info. The desktop redirect is disabled.'), $logger->messages(LogLevel::ERROR));
        self::assertSame([], $logger->messages(LogLevel::WARNING));
    }

    public function testACrossHostRedirectWarnsWhenTheCookieHasNoDomain(): void
    {
        $logger = new TestLogger();
        $policy = new RedirectPolicy(['tablet' => self::options('https://t.example.com:8443')], new ViewNames(), new SwitchUrlGenerator(), 'dv', $logger);

        self::assertNotNull($policy->deviceRedirect(Request::create('http://www.example.com/'), DeviceType::Tablet));
        self::assertNull($policy->deviceRedirect(Request::create('https://t.example.com:8443/'), DeviceType::Tablet));
        self::assertSame(['The tablet redirect goes to t.example.com:8443, but the dv cookie has no domain, so that host cannot read the remembered view. Set device_view.remember.cookie.domain to a domain both hosts share.'], $logger->messages(LogLevel::WARNING));
    }

    public function testNoWarningWhenTheCookieHasADomain(): void
    {
        $logger = new TestLogger();
        $policy = new RedirectPolicy(['tablet' => self::options('https://t.example.com')], new ViewNames(), new SwitchUrlGenerator(), null, $logger);

        self::assertNotNull($policy->deviceRedirect(Request::create('http://www.example.com/'), DeviceType::Tablet));
        self::assertSame([], $logger->records);
    }

    public function testTheLoggerIsOptional(): void
    {
        $request = Request::create('http://www.example.com/');

        self::assertNull(new RedirectPolicy(['mobile' => self::options('m.example.com')], new ViewNames(), new SwitchUrlGenerator(), 'device_view')->deviceRedirect($request, DeviceType::Mobile));
        self::assertNotNull(new RedirectPolicy(['mobile' => self::options('https://m.example.com')], new ViewNames(), new SwitchUrlGenerator(), 'device_view')->deviceRedirect($request, DeviceType::Mobile));
    }

    /**
     * @param array<value-of<DeviceType>, array{host: string, action: RedirectAction, status: int, keep_query: bool}> $redirects
     */
    private static function policy(array $redirects): RedirectPolicy
    {
        return new RedirectPolicy($redirects, new ViewNames(), new SwitchUrlGenerator());
    }

    /**
     * @return array{host: string, action: RedirectAction, status: int, keep_query: bool}
     */
    private static function options(string $host, RedirectAction $action = RedirectAction::Redirect, int $status = 302, bool $keepQuery = true): array
    {
        return ['host' => $host, 'action' => $action, 'status' => $status, 'keep_query' => $keepQuery];
    }
}
