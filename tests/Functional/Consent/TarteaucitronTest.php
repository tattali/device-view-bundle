<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional\Consent;

use DeviceViewBundle\Tests\Consent\ConsentFixtures;
use DeviceViewBundle\Tests\Functional\AssertsCookies;
use DeviceViewBundle\Tests\Functional\ChecksConsent;
use DeviceViewBundle\Tests\Functional\HandlesRequests;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The tarteaucitron reader through the kernel: the CON-TAC rows of docs/scenarios.md.
 */
final class TarteaucitronTest extends TestCase
{
    use AssertsCookies;
    use ChecksConsent;
    use HandlesRequests;

    private const array TAC = ['consent' => ['provider' => 'tarteaucitron']];

    #[Group('CON-TAC-01')]
    public function testGranted(): void
    {
        $this->assertConsentRow(self::TAC, ConsentFixtures::cookies('tarteaucitron=!device_view=true'), true);
    }

    #[Group('CON-TAC-02')]
    public function testGrantedAmongOtherServices(): void
    {
        $this->assertConsentRow(self::TAC, ConsentFixtures::cookies('tarteaucitron=!gtag=true!device_view=true!youtube=false'), true);
    }

    #[Group('CON-TAC-03')]
    public function testDenied(): void
    {
        $this->assertConsentRow(self::TAC, ConsentFixtures::cookies('tarteaucitron=!device_view=false'), false);
    }

    #[Group('CON-TAC-04')]
    public function testWaiting(): void
    {
        $this->assertConsentRow(self::TAC, ConsentFixtures::cookies('tarteaucitron=!device_view=wait'), null);
    }

    #[Group('CON-TAC-05')]
    public function testServiceAbsent(): void
    {
        $this->assertConsentRow(self::TAC, ConsentFixtures::cookies('tarteaucitron=!gtag=true'), null);
    }

    #[Group('CON-TAC-06')]
    public function testNoCookie(): void
    {
        $this->assertConsentRow(self::TAC, [], null);
    }

    #[Group('CON-TAC-07')]
    public function testExactKeyMatchOnly(): void
    {
        $this->assertConsentRow(self::TAC, ConsentFixtures::cookies('tarteaucitron=!device_view_extra=true'), null);
    }

    #[Group('CON-TAC-08')]
    public function testGarbage(): void
    {
        $this->assertConsentRow(self::TAC, ConsentFixtures::cookies('tarteaucitron=%%%!!!=='), null);
    }

    #[Group('CON-TAC-09')]
    public function testArrayCookie(): void
    {
        $this->assertConsentRow(self::TAC, ['tarteaucitron' => ['x' => '1']], null);
    }

    #[Group('CON-TAC-10')]
    public function testCustomCookieName(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'tarteaucitron', 'cookie' => 'tac']],
            ConsentFixtures::cookies('tac=!device_view=true; tarteaucitron=!device_view=false'),
            true,
        );
    }

    #[Group('CON-TAC-11')]
    public function testCustomKey(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'tarteaucitron', 'key' => 'dv_pref']],
            ConsentFixtures::cookies('tarteaucitron=!dv_pref=true!device_view=false'),
            true,
        );
    }
}
