<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional\Consent;

use DeviceViewBundle\Consent\KlaroConsentChecker;
use DeviceViewBundle\Tests\Functional\AssertsCookies;
use DeviceViewBundle\Tests\Functional\ChecksConsent;
use DeviceViewBundle\Tests\Functional\HandlesRequests;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The Klaro reader through the kernel: the CON-KLA rows of docs/scenarios.md. The cookies are
 * given as PHP decodes them.
 */
final class KlaroTest extends TestCase
{
    use AssertsCookies;
    use ChecksConsent;
    use HandlesRequests;

    private const array KLARO = ['consent' => ['provider' => 'klaro']];

    public function testTheProviderNamesTheBuiltInReader(): void
    {
        $this->boot(self::KLARO);

        self::assertInstanceOf(KlaroConsentChecker::class, $this->service('test.device_view.consent_checker'));
        self::assertSame($this->service('test.device_view.consent_checker'), $this->service('test.consent_checker_interface'));
    }

    #[Group('CON-KLA-01')]
    public function testGranted(): void
    {
        $this->assertConsentRow(self::KLARO, ['klaro' => '{"device_view":true,"analytics":false}'], true);
    }

    #[Group('CON-KLA-02')]
    public function testDenied(): void
    {
        $this->assertConsentRow(self::KLARO, ['klaro' => '{"device_view":false}'], false);
    }

    #[Group('CON-KLA-03')]
    public function testServiceAbsent(): void
    {
        $this->assertConsentRow(self::KLARO, ['klaro' => '{"analytics":true}'], null);
    }

    #[Group('CON-KLA-04')]
    public function testNoCookie(): void
    {
        $this->assertConsentRow(self::KLARO, [], null);
    }

    #[Group('CON-KLA-05')]
    public function testUrlEncodedOnce(): void
    {
        $this->assertConsentRow(self::KLARO, ['klaro' => '%7B%22device_view%22%3Atrue%7D'], true);
    }

    #[Group('CON-KLA-06')]
    public function testUrlEncodedTwice(): void
    {
        $this->assertConsentRow(self::KLARO, ['klaro' => '%257B%2522device_view%2522%253Atrue%257D'], true);
    }

    #[Group('CON-KLA-07')]
    public function testNotABoolean(): void
    {
        $this->assertConsentRow(self::KLARO, ['klaro' => '{"device_view":"yes"}'], null);
    }

    #[Group('CON-KLA-08')]
    public function testGarbage(): void
    {
        $this->assertConsentRow(self::KLARO, ['klaro' => 'not-json'], null);
    }

    #[Group('CON-KLA-09')]
    public function testArrayCookie(): void
    {
        $this->assertConsentRow(self::KLARO, ['klaro' => ['device_view' => '1']], null);
    }

    #[Group('CON-KLA-10')]
    public function testCustomCookieNameAndKey(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'klaro', 'cookie' => 'klaro-consent', 'key' => 'views']],
            ['klaro-consent' => '{"views":true}'],
            true,
        );
    }
}
