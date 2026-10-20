<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional\Consent;

use DeviceViewBundle\Tests\Functional\AssertsCookies;
use DeviceViewBundle\Tests\Functional\ChecksConsent;
use DeviceViewBundle\Tests\Functional\HandlesRequests;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The OneTrust reader through the kernel: the CON-OT rows of docs/scenarios.md. The cookies are
 * given as PHP decodes them, as the rows write them.
 */
final class OneTrustTest extends TestCase
{
    use AssertsCookies;
    use ChecksConsent;
    use HandlesRequests;

    private const array OT = ['consent' => ['provider' => 'onetrust']];

    #[Group('CON-OT-01')]
    public function testGranted(): void
    {
        $this->assertConsentRow(self::OT, ['OptanonConsent' => self::ot('C0001:1,C0002:0,C0003:1,C0004:0')], true);
    }

    #[Group('CON-OT-02')]
    public function testDenied(): void
    {
        $this->assertConsentRow(self::OT, ['OptanonConsent' => self::ot('C0001:1,C0002:0,C0003:0,C0004:0')], false);
    }

    #[Group('CON-OT-03')]
    public function testGroupAbsent(): void
    {
        $this->assertConsentRow(self::OT, ['OptanonConsent' => self::ot('C0001:1,C0002:1')], null);
    }

    #[Group('CON-OT-04')]
    public function testNoGroupsParameter(): void
    {
        $this->assertConsentRow(self::OT, ['OptanonConsent' => 'isGpcEnabled=0&interactionCount=0'], null);
    }

    #[Group('CON-OT-05')]
    public function testNoCookie(): void
    {
        $this->assertConsentRow(self::OT, [], null);
    }

    #[Group('CON-OT-06')]
    public function testValueStillUrlEncoded(): void
    {
        $this->assertConsentRow(self::OT, ['OptanonConsent' => 'isGpcEnabled%3D0%26groups%3DC0001%253A1%252CC0003%253A1'], true);
    }

    #[Group('CON-OT-07')]
    public function testGarbage(): void
    {
        $this->assertConsentRow(self::OT, ['OptanonConsent' => '%%%'], null);
    }

    #[Group('CON-OT-08')]
    public function testArrayCookie(): void
    {
        $this->assertConsentRow(self::OT, ['OptanonConsent' => ['groups' => 'C0003:1']], null);
    }

    #[Group('CON-OT-09')]
    public function testCustomKey(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'onetrust', 'key' => 'C0002']],
            ['OptanonConsent' => self::ot('C0001:1,C0002:0,C0003:1,C0004:0')],
            false,
        );
    }

    #[Group('CON-OT-10')]
    public function testCustomCookieName(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'onetrust', 'cookie' => 'OptanonConsentSite']],
            ['OptanonConsentSite' => self::ot('C0003:1')],
            true,
        );
    }

    /**
     * The value `<OT groups=…>` of the CON-OT rows.
     */
    private static function ot(string $groups): string
    {
        return 'isGpcEnabled=0&datestamp=Tue Oct 20 2026 10:00:00 GMT+0200&version=202409.1.0&isIABGlobal=false&hosts=&consentId=0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0&interactionCount=1&landingPath=NotLandingPage&groups='.$groups;
    }
}
