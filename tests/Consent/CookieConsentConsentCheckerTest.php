<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Consent;

use DeviceViewBundle\Consent\CookieConsentConsentChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The CookieConsent v3 reader on its own: the fixtures of tests/Fixtures/consent/cookieconsent
 * and the edge cases of its parser. CookieConsentTest checks the CON-CC rows through the kernel.
 */
final class CookieConsentConsentCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool|null}>
     */
    public static function fixtures(): iterable
    {
        return ConsentFixtures::of('cookieconsent');
    }

    /**
     * @param array<string, mixed> $cookies
     */
    #[DataProvider('fixtures')]
    public function testFixtures(array $cookies, ?bool $expected): void
    {
        self::assertSame($expected, new CookieConsentConsentChecker()->hasConsent(self::request($cookies)));
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function serviceFixtures(): iterable
    {
        yield 'every category and service accepted' => ['accept-all', true];
        yield 'everything rejected' => ['reject-all', false];
        yield 'the category with all its services' => ['preferences-only', true];
        yield 'the category with the service only' => ['preferences-device-view-service-only', true];
        yield 'the category with another service only' => ['preferences-other-service-only', false];
        yield 'another category only' => ['analytics-only', false];
    }

    #[DataProvider('serviceFixtures')]
    public function testACategoryServiceKeyOnTheFixtures(string $fixture, bool $expected): void
    {
        $checker = new CookieConsentConsentChecker(key: 'preferences.device_view');

        self::assertSame($expected, $checker->hasConsent(self::request(self::fixture($fixture))));
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function values(): iterable
    {
        yield 'the category alone' => ['{"categories":["preferences"]}', true];
        yield 'the category among others' => ['{"categories":["necessary","preferences","analytics"]}', true];
        yield 'no category' => ['{"categories":[]}', false];
        yield 'a category ending like it' => ['{"categories":["my_preferences"]}', false];
        yield 'a category starting like it' => ['{"categories":["preferences_extra"]}', false];
        yield 'another case' => ['{"categories":["Preferences"]}', false];
        yield 'categories null' => ['{"categories":null}', null];
        yield 'categories a number' => ['{"categories":1}', null];
        yield 'categories an object' => ['{"categories":{"a":"preferences"}}', null];
        yield 'a category that is a list' => ['{"categories":[["preferences"]]}', null];
        yield 'a category that is null' => ['{"categories":["preferences",null]}', null];
        yield 'no categories' => ['{"services":{"preferences":["device_view"]}}', null];
        yield 'an empty object' => ['{}', null];
        yield 'a list' => ['[{"categories":["preferences"]}]', null];
        yield 'a number' => ['1', null];
        yield 'true' => ['true', null];
        yield 'null' => ['null', null];
        yield 'an empty value' => ['', null];
        yield 'JSON with trailing garbage' => ['{"categories":["preferences"]}x', null];
        yield 'still URL-encoded' => ['%7B%22categories%22%3A%5B%22preferences%22%5D%7D', true];
        yield 'still URL-encoded, not accepted' => ['%7B%22categories%22%3A%5B%22necessary%22%5D%7D', false];
        yield 'URL-encoded garbage' => ['%7Bnot%20json', null];
        yield 'encoded three times' => ['%257B%2522categories%2522%253A%255B%2522preferences%2522%255D%257D', null];
        yield 'too deep to decode' => [str_repeat('[', 600).str_repeat(']', 600), null];
        yield 'invalid UTF-8' => ["{\"categories\":[\"preferences\",\"\xff\"]}", null];
    }

    #[DataProvider('values')]
    public function testValues(string $value, ?bool $expected): void
    {
        self::assertSame($expected, new CookieConsentConsentChecker()->hasConsent(self::request(['cc_cookie' => $value])));
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function serviceValues(): iterable
    {
        yield 'the service accepted' => ['{"categories":["preferences"],"services":{"preferences":["device_view"]}}', true];
        yield 'the service among others' => ['{"categories":["preferences"],"services":{"preferences":["theme","device_view"]}}', true];
        yield 'no service accepted' => ['{"categories":["preferences"],"services":{"preferences":[]}}', false];
        yield 'another service accepted' => ['{"categories":["preferences"],"services":{"preferences":["theme"]}}', false];
        yield 'the service under another category' => ['{"categories":["preferences"],"services":{"analytics":["device_view"]}}', false];
        yield 'the category not accepted' => ['{"categories":["necessary"],"services":{"preferences":["device_view"]}}', false];
        yield 'no services, as CookieConsent reads it' => ['{"categories":["preferences"]}', false];
        yield 'empty services' => ['{"categories":["preferences"],"services":{}}', false];
        yield 'services a list' => ['{"categories":["preferences"],"services":[["device_view"]]}', null];
        yield 'services a string' => ['{"categories":["preferences"],"services":"device_view"}', null];
        yield 'services null, read as none' => ['{"categories":["preferences"],"services":null}', false];
        yield 'the category services a string' => ['{"categories":["preferences"],"services":{"preferences":"device_view"}}', null];
        yield 'the category services an object' => ['{"categories":["preferences"],"services":{"preferences":{"a":"device_view"}}}', null];
        yield 'the category services null, read as none' => ['{"categories":["preferences"],"services":{"preferences":null}}', false];
        yield 'a service that is not a string' => ['{"categories":["preferences"],"services":{"preferences":["device_view",1]}}', null];
        yield 'garbage categories' => ['{"categories":"preferences","services":{"preferences":["device_view"]}}', null];
        yield 'still URL-encoded' => ['%7B%22categories%22%3A%5B%22preferences%22%5D%2C%22services%22%3A%7B%22preferences%22%3A%5B%22device_view%22%5D%7D%7D', true];
    }

    #[DataProvider('serviceValues')]
    public function testACategoryServiceKey(string $value, ?bool $expected): void
    {
        $checker = new CookieConsentConsentChecker(key: 'preferences.device_view');

        self::assertSame($expected, $checker->hasConsent(self::request(['cc_cookie' => $value])));
    }

    public function testTheServiceIsWhatFollowsTheFirstDot(): void
    {
        $checker = new CookieConsentConsentChecker(key: 'preferences.device_view.v2');

        self::assertTrue($checker->hasConsent(self::request(['cc_cookie' => '{"categories":["preferences"],"services":{"preferences":["device_view.v2"]}}'])));
        self::assertFalse($checker->hasConsent(self::request(['cc_cookie' => '{"categories":["preferences"],"services":{"preferences":["device_view"]}}'])));
    }

    public function testAStillEncodedValueIsDecodedLikeDecodeUriComponent(): void
    {
        // decodeURIComponent() keeps "+", which urldecode() would turn into a space
        $checker = new CookieConsentConsentChecker(key: 'a+b');

        self::assertTrue($checker->hasConsent(self::request(['cc_cookie' => '%7B%22categories%22%3A%5B%22a+b%22%5D%7D'])));
    }

    public function testADecodedValueIsNotDecodedAgain(): void
    {
        $checker = new CookieConsentConsentChecker(key: 'a%20b');

        self::assertTrue($checker->hasConsent(self::request(['cc_cookie' => '{"categories":["a%20b"]}'])));
    }

    public function testNoCookie(): void
    {
        self::assertNull(new CookieConsentConsentChecker()->hasConsent(self::request([])));
    }

    public function testAnArrayCookie(): void
    {
        self::assertNull(new CookieConsentConsentChecker()->hasConsent(self::request(['cc_cookie' => ['categories' => 'preferences']])));
    }

    public function testACustomCookieName(): void
    {
        $request = self::request(['cc_site' => '{"categories":["preferences"]}', 'cc_cookie' => '{"categories":[]}']);

        self::assertTrue(new CookieConsentConsentChecker(cookie: 'cc_site')->hasConsent($request));
        self::assertFalse(new CookieConsentConsentChecker()->hasConsent($request));
    }

    public function testACustomKey(): void
    {
        $request = self::request(['cc_cookie' => '{"categories":["analytics"]}']);

        self::assertTrue(new CookieConsentConsentChecker(key: 'analytics')->hasConsent($request));
        self::assertFalse(new CookieConsentConsentChecker()->hasConsent($request));
    }

    public function testNullStandsForTheDefaults(): void
    {
        $request = self::request(['cc_cookie' => '{"categories":["preferences"],"services":{"preferences":[]}}']);

        self::assertTrue(new CookieConsentConsentChecker(null, null)->hasConsent($request));
    }

    /**
     * @return array<string, mixed>
     */
    private static function fixture(string $name): array
    {
        $lines = file(\dirname(__DIR__).'/Fixtures/consent/cookieconsent/3.1.0/'.$name.'.txt', \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        $header = array_find($lines, static fn (string $line): bool => str_starts_with($line, 'Cookie: '));
        self::assertIsString($header);

        return ConsentFixtures::cookies(substr($header, \strlen('Cookie: ')));
    }

    /**
     * @param array<string, mixed> $cookies
     */
    private static function request(array $cookies): Request
    {
        return Request::create('http://localhost/view', cookies: $cookies);
    }
}
