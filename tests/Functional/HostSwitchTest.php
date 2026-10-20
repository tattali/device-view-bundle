<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Tests\App\TestLogger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;

/**
 * A switch to a view served on another host (design.md section 5, step 3), through the kernel:
 * the switch redirect goes where a device redirect of that view would go.
 */
final class HostSwitchTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    private const array M = ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com']];
    private const array D = ['desktop' => ['enabled' => true, 'host' => 'https://www.example.com']];

    #[Group('SW-17')]
    public function testSwitchToAViewServedByAnotherHost(): void
    {
        $this->boot(['redirect' => self::M]);

        $response = $this->handle(self::request('http://www.example.com/blog/post?a=1&device_view=mobile'));

        self::assertRedirect('https://m.example.com/blog/post?a=1&device_view=mobile', 302, $response);
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('SW-18')]
    public function testSwitchArrivingOnTheTargetHost(): void
    {
        $this->boot(['redirect' => self::M]);

        $response = $this->handle(self::request('https://m.example.com/blog/post?a=1&device_view=mobile'));

        self::assertRedirect('/blog/post?a=1', 302, $response);
        self::assertSetCookie(self::scs('mobile'), $response);
    }

    #[Group('SW-19')]
    public function testSwitchBackToDesktopFromTheMobileHost(): void
    {
        $this->boot(['redirect' => self::M + self::D]);

        $response = $this->handle(self::request('https://m.example.com/blog/post?device_view=desktop', 'iphone'));

        self::assertRedirect('https://www.example.com/blog/post?device_view=desktop', 302, $response);
        self::assertSetCookie(self::scs('desktop'), $response);
    }

    #[Group('SW-20')]
    public function testTheRedirectedViewsHostEqualsTheCurrentHost(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertRedirect('/view', 302, $this->handle(self::request('https://m.example.com/view?device_view=mobile')));
    }

    public function testASwitchToAViewWithoutAHostStaysOnTheCurrentHost(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertRedirect('/view', 302, $this->handle(self::request('https://m.example.com/view?device_view=desktop', 'iphone')));
    }

    public function testTheSwitchStatusAndTheHostOptionsApply(): void
    {
        $this->boot([
            'switch' => ['status' => 307],
            'redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com/shop', 'status' => 301, 'keep_query' => false]],
        ]);

        self::assertRedirect('https://m.example.com/shop/blog/post?device_view=mobile', 307, $this->handle(self::request('http://www.example.com/blog/post?a=1&device_view=mobile')));
    }

    public function testTargetHomeGoesToTheHomeOfTheOtherHost(): void
    {
        $this->boot(['switch' => ['target' => 'home'], 'redirect' => self::M]);

        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/blog/post?a=1&device_view=mobile')));
    }

    public function testRedirectWithoutPathGoesToTheHomeOfTheOtherHost(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'action' => 'redirect_without_path']]]);

        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/blog/post?a=1&device_view=mobile')));
    }

    public function testActionNoneKeepsTheSwitchOnTheCurrentHost(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'action' => 'none']]]);

        self::assertRedirect('/blog/post?a=1', 302, $this->handle(self::request('http://www.example.com/blog/post?a=1&device_view=mobile')));
    }

    public function testTabletAsMobileSwitchesATabletToTheMobileHost(): void
    {
        $this->boot(['tablet_as_mobile' => true, 'redirect' => self::M]);

        $response = $this->handle(self::request('http://www.example.com/view?device_view=tablet'));

        self::assertRedirect('https://m.example.com/view?device_view=mobile', 302, $response);
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    public function testACrossHostSwitchWarnsWithoutACookieDomain(): void
    {
        $this->boot(['redirect' => self::M], ['logger' => TestLogger::class]);

        self::assertSame(302, $this->handle(self::request('http://www.example.com/view?device_view=mobile'))->getStatusCode());
        $logger = $this->service('logger');
        self::assertInstanceOf(TestLogger::class, $logger);
        self::assertSame(['The mobile redirect goes to m.example.com, but the device_view cookie has no domain, so that host cannot read the remembered view. Set device_view.remember.cookie.domain to a domain both hosts share.'], $logger->messages(LogLevel::WARNING));
    }

    private static function assertRedirect(string $location, int $status, Response $response): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($location, $response->headers->get('Location'));
    }
}
