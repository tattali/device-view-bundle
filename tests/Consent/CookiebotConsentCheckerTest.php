<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Consent;

use DeviceViewBundle\Consent\CookiebotConsentChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Cookiebot reader on its own: the fixtures of tests/Fixtures/consent/cookiebot and the
 * edge cases of its parser. CookiebotTest checks the CON-CB rows through the kernel.
 */
final class CookiebotConsentCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool|null}>
     */
    public static function fixtures(): iterable
    {
        return ConsentFixtures::of('cookiebot');
    }

    /**
     * @param array<string, mixed> $cookies
     */
    #[DataProvider('fixtures')]
    public function testFixtures(array $cookies, ?bool $expected): void
    {
        self::assertSame($expected, new CookiebotConsentChecker()->hasConsent(self::request($cookies)));
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function values(): iterable
    {
        yield 'no consent needed' => ['-1', true];
        yield 'nothing accepted' => ['0', false];
        yield 'granted, JavaScript-style' => ["{stamp:'x',necessary:true,preferences:true}", true];
        yield 'denied, JavaScript-style' => ["{stamp:'x',necessary:true,preferences:false}", false];
        yield 'the key last' => ['{necessary:true,preferences:true}', true];
        yield 'the key first' => ['{preferences:false,necessary:true}', false];
        yield 'quoted booleans' => ['{"preferences":"true"}', true];
        yield 'quoted false' => ['{"preferences":"false"}', false];
        yield 'JSON booleans left as they are' => ['{preferences: true }', true];
        yield 'JSON false left as it is' => ['{preferences: false }', false];
        yield 'spaces after the colon and the comma' => ['{necessary: true, preferences: true}', true];
        // Cookiebot's normalisation quotes a name only when the colon follows it
        yield 'a space before the colon' => ['{preferences :true}', null];
        yield 'single-quoted booleans' => ["{preferences:'true'}", true];
        yield 'a number for a boolean' => ['{preferences:1}', null];
        yield 'another word' => ['{preferences:yes}', null];
        yield 'upper case' => ['{preferences:TRUE}', null];
        yield 'null' => ['{preferences:null}', null];
        yield 'an object for a boolean' => ['{preferences:{a:true}}', null];
        yield 'a list for a boolean' => ['{preferences:[true]}', null];
        yield 'the key absent' => ["{stamp:'x',necessary:true}", null];
        yield 'a longer key' => ['{preferences_extra:true}', null];
        yield 'a key ending like it' => ['{my_preferences:true}', null];
        yield 'an empty object' => ['{}', null];
        yield 'a list' => ['[true]', null];
        yield 'a list of objects' => ['[{preferences:true}]', null];
        yield 'a JSON string' => ['"-1"', null];
        yield 'a JSON boolean' => ['true', null];
        yield 'another number' => ['1', null];
        yield 'minus one with a space' => [' -1', null];
        yield 'zero with a space' => ['0 ', null];
        yield 'an empty value' => ['', null];
        yield 'truncated' => ['{preferences:', null];
        yield 'not closed' => ['{preferences:true', null];
        yield 'still URL-encoded' => ['%7Bpreferences%3Atrue%7D', null];
        yield 'invalid UTF-8' => ["{stamp:'\xFF',preferences:true}", null];
    }

    #[DataProvider('values')]
    public function testValues(string $value, ?bool $expected): void
    {
        self::assertSame($expected, new CookiebotConsentChecker()->hasConsent(self::request(['CookieConsent' => $value])));
    }

    public function testNoCookie(): void
    {
        self::assertNull(new CookiebotConsentChecker()->hasConsent(self::request([])));
    }

    public function testAnArrayCookie(): void
    {
        self::assertNull(new CookiebotConsentChecker()->hasConsent(self::request(['CookieConsent' => ['preferences' => 'true']])));
    }

    public function testAValueAsLongAsABrowserStoresIsRead(): void
    {
        $value = self::paddedTo(4096, '{preferences:true,stamp:\'', '\'}');

        self::assertTrue(new CookiebotConsentChecker()->hasConsent(self::request(['CookieConsent' => $value])));
    }

    public function testALongerValueIsNotRead(): void
    {
        $value = self::paddedTo(4097, '{preferences:true,stamp:\'', '\'}');

        self::assertNull(new CookiebotConsentChecker()->hasConsent(self::request(['CookieConsent' => $value])));
    }

    public function testACustomCookieName(): void
    {
        $request = self::request(['CookieConsentSite' => '-1', 'CookieConsent' => '0']);

        self::assertTrue(new CookiebotConsentChecker(cookie: 'CookieConsentSite')->hasConsent($request));
        self::assertFalse(new CookiebotConsentChecker()->hasConsent($request));
    }

    public function testACustomKey(): void
    {
        $request = self::request(['CookieConsent' => '{preferences:false,statistics:true}']);

        self::assertTrue(new CookiebotConsentChecker(key: 'statistics')->hasConsent($request));
        self::assertFalse(new CookiebotConsentChecker()->hasConsent($request));
    }

    public function testACustomKeyIsGrantedWhenNoConsentIsNeeded(): void
    {
        self::assertTrue(new CookiebotConsentChecker(key: 'marketing')->hasConsent(self::request(['CookieConsent' => '-1'])));
        self::assertFalse(new CookiebotConsentChecker(key: 'marketing')->hasConsent(self::request(['CookieConsent' => '0'])));
    }

    public function testNullStandsForTheDefaults(): void
    {
        $request = self::request(['CookieConsent' => '{preferences:true}']);

        self::assertTrue(new CookiebotConsentChecker(null, null)->hasConsent($request));
    }

    public function testAKeyIsMatchedLiterally(): void
    {
        $checker = new CookiebotConsentChecker(key: 'a.b');

        self::assertTrue($checker->hasConsent(self::request(['CookieConsent' => '{"a.b":true}'])));
        self::assertNull($checker->hasConsent(self::request(['CookieConsent' => '{axb:true}'])));
    }

    private static function paddedTo(int $length, string $start, string $end): string
    {
        return $start.str_repeat('A', $length - \strlen($start) - \strlen($end)).$end;
    }

    /**
     * @param array<string, mixed> $cookies
     */
    private static function request(array $cookies): Request
    {
        return Request::create('http://localhost/view', cookies: $cookies);
    }
}
