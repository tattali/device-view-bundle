<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional\Consent;

use DeviceViewBundle\Consent\CookiebotConsentChecker;
use DeviceViewBundle\Tests\Consent\ConsentFixtures;
use DeviceViewBundle\Tests\Functional\AssertsCookies;
use DeviceViewBundle\Tests\Functional\ChecksConsent;
use DeviceViewBundle\Tests\Functional\HandlesRequests;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The Cookiebot reader through the kernel: the CON-CB rows of docs/scenarios.md.
 */
final class CookiebotTest extends TestCase
{
    use AssertsCookies;
    use ChecksConsent;
    use HandlesRequests;

    private const array CB = ['consent' => ['provider' => 'cookiebot']];

    private const string GRANTED = "{stamp:'k7vR4UXCrdIN0JRQ6ZJhvUh1yz6UvC4gOGBQafPnz6ZLKNQiQJn1Ow==',necessary:true,preferences:true,statistics:false,marketing:false,method:'explicit',ver:1,utc:1792483200000,region:'fr'}";

    public function testTheProviderIsTheCookiebotReader(): void
    {
        $this->boot(self::CB);

        self::assertInstanceOf(CookiebotConsentChecker::class, $this->service('test.device_view.consent_checker'));
        self::assertSame($this->service('test.device_view.consent_checker'), $this->service('test.consent_checker_interface'));
    }

    #[Group('CON-CB-01')]
    public function testNoConsentNeeded(): void
    {
        $this->assertConsentRow(self::CB, ConsentFixtures::cookies('CookieConsent=-1'), true);
    }

    #[Group('CON-CB-02')]
    public function testGranted(): void
    {
        $this->assertConsentRow(self::CB, ConsentFixtures::cookies('CookieConsent='.self::GRANTED), true);
    }

    #[Group('CON-CB-03')]
    public function testDenied(): void
    {
        $denied = str_replace('preferences:true', 'preferences:false', self::GRANTED);

        $this->assertConsentRow(self::CB, ConsentFixtures::cookies('CookieConsent='.$denied), false);
    }

    #[Group('CON-CB-04')]
    public function testKeyAbsent(): void
    {
        $this->assertConsentRow(self::CB, ConsentFixtures::cookies("CookieConsent={stamp:'x',necessary:true}"), null);
    }

    #[Group('CON-CB-05')]
    public function testNoCookie(): void
    {
        $this->assertConsentRow(self::CB, [], null);
    }

    #[Group('CON-CB-06')]
    public function testGarbage(): void
    {
        $this->assertConsentRow(self::CB, ConsentFixtures::cookies('CookieConsent={preferences:'), null);
    }

    #[Group('CON-CB-07')]
    public function testNothingAccepted(): void
    {
        $this->assertConsentRow(self::CB, ConsentFixtures::cookies('CookieConsent=0'), false);
    }

    #[Group('CON-CB-08')]
    public function testArrayCookie(): void
    {
        $this->assertConsentRow(self::CB, ['CookieConsent' => ['preferences' => 'true']], null);
    }

    #[Group('CON-CB-09')]
    public function testCustomKey(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'cookiebot', 'key' => 'statistics']],
            ConsentFixtures::cookies('CookieConsent='.self::GRANTED),
            false,
        );
    }

    #[Group('CON-CB-10')]
    public function testCustomCookieName(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'cookiebot', 'cookie' => 'CookieConsentSite']],
            ConsentFixtures::cookies('CookieConsentSite=-1'),
            true,
        );
    }
}
