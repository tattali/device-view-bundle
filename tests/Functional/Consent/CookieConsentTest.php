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
 * The CookieConsent v3 reader through the kernel: the CON-CC rows of docs/scenarios.md.
 *
 * The rows give the cookie as PHP decodes it, so the raw headers below are URL-encoded the way
 * CookieConsent writes them, with encodeURIComponent().
 */
final class CookieConsentTest extends TestCase
{
    use AssertsCookies;
    use ChecksConsent;
    use HandlesRequests;

    private const array CC = ['consent' => ['provider' => 'cookieconsent']];

    private const array CC_SERVICE = ['consent' => ['provider' => 'cookieconsent', 'key' => 'preferences.device_view']];

    #[Group('CON-CC-01')]
    public function testCategoryAccepted(): void
    {
        $this->assertConsentRow(self::CC, self::cookies('{"categories":["necessary","preferences"],"revision":0}'), true);
    }

    #[Group('CON-CC-02')]
    public function testCategoryNotAccepted(): void
    {
        $this->assertConsentRow(self::CC, self::cookies('{"categories":["necessary"],"revision":0}'), false);
    }

    #[Group('CON-CC-03')]
    public function testNoCookie(): void
    {
        $this->assertConsentRow(self::CC, [], null);
    }

    #[Group('CON-CC-04')]
    public function testValueStillUrlEncoded(): void
    {
        $this->assertConsentRow(self::CC, ['cc_cookie' => '%7B%22categories%22%3A%5B%22preferences%22%5D%7D'], true);
    }

    #[Group('CON-CC-05')]
    public function testServiceAccepted(): void
    {
        $this->assertConsentRow(self::CC_SERVICE, self::cookies('{"categories":["preferences"],"services":{"preferences":["device_view"]}}'), true);
    }

    #[Group('CON-CC-06')]
    public function testServiceNotAccepted(): void
    {
        $this->assertConsentRow(self::CC_SERVICE, self::cookies('{"categories":["preferences"],"services":{"preferences":[]}}'), false);
    }

    #[Group('CON-CC-07')]
    public function testServiceKeyWithCategoryNotAccepted(): void
    {
        $this->assertConsentRow(self::CC_SERVICE, self::cookies('{"categories":["necessary"],"services":{"necessary":[]}}'), false);
    }

    #[Group('CON-CC-08')]
    public function testGarbage(): void
    {
        $this->assertConsentRow(self::CC, self::cookies('{not json'), null);
    }

    #[Group('CON-CC-09')]
    public function testJsonWithoutCategories(): void
    {
        $this->assertConsentRow(self::CC, self::cookies('{"revision":0}'), null);
    }

    #[Group('CON-CC-10')]
    public function testCategoriesNotAList(): void
    {
        $this->assertConsentRow(self::CC, self::cookies('{"categories":"preferences"}'), null);
    }

    #[Group('CON-CC-11')]
    public function testArrayCookie(): void
    {
        $this->assertConsentRow(self::CC, ['cc_cookie' => ['categories' => 'preferences']], null);
    }

    #[Group('CON-CC-12')]
    public function testCustomCookieNameAndKey(): void
    {
        $this->assertConsentRow(
            ['consent' => ['provider' => 'cookieconsent', 'cookie' => 'cc_site', 'key' => 'analytics']],
            ConsentFixtures::cookies('cc_site='.rawurlencode('{"categories":["analytics"]}').'; cc_cookie='.rawurlencode('{"categories":["necessary"]}')),
            true,
        );
    }

    /**
     * The cc_cookie cookie as PHP decodes it from the header CookieConsent writes.
     *
     * @return array<string, mixed>
     */
    private static function cookies(string $json): array
    {
        $cookies = ConsentFixtures::cookies('cc_cookie='.rawurlencode($json));
        self::assertSame(['cc_cookie' => $json], $cookies);

        return $cookies;
    }
}
