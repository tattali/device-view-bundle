<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Consent;

use DeviceViewBundle\Consent\TarteaucitronConsentChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The tarteaucitron reader on its own: the fixtures of tests/Fixtures/consent/tarteaucitron and
 * the edge cases of its parser. ConsentTest checks the CON-TAC rows through the kernel.
 */
final class TarteaucitronConsentCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool|null}>
     */
    public static function fixtures(): iterable
    {
        return ConsentFixtures::of('tarteaucitron');
    }

    /**
     * @param array<string, mixed> $cookies
     */
    #[DataProvider('fixtures')]
    public function testFixtures(array $cookies, ?bool $expected): void
    {
        self::assertSame($expected, new TarteaucitronConsentChecker()->hasConsent(self::request($cookies)));
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function values(): iterable
    {
        yield 'granted first' => ['!device_view=true!gtag=false', true];
        yield 'denied among others' => ['!gtag=true!device_view=false!youtube=true', false];
        yield 'waiting among others' => ['!gtag=true!device_view=wait', null];
        yield 'a longer key granted, the key denied' => ['!device_view_extra=true!device_view=false', false];
        yield 'a key ending like it' => ['!my_device_view=true', null];
        yield 'an unknown status' => ['!device_view=yes', null];
        yield 'a status with a suffix' => ['!device_view=truex', null];
        yield 'a status with spaces' => ['!device_view= true', null];
        yield 'no status' => ['!device_view=', null];
        yield 'no equal sign' => ['!device_view', null];
        yield 'the key twice, same status' => ['!device_view=true!device_view=true', null];
        yield 'another segment without equal sign' => ['!gtag!device_view=true', true];
        yield 'no leading separator, but the key later' => ['gtag=true!device_view=true', null];
        yield 'only the separator' => ['!', null];
        yield 'a URL-encoded separator left as is' => ['%21device_view=true', null];
    }

    #[DataProvider('values')]
    public function testValues(string $value, ?bool $expected): void
    {
        self::assertSame($expected, new TarteaucitronConsentChecker()->hasConsent(self::request(['tarteaucitron' => $value])));
    }

    public function testNoCookie(): void
    {
        self::assertNull(new TarteaucitronConsentChecker()->hasConsent(self::request([])));
    }

    public function testAnArrayCookie(): void
    {
        self::assertNull(new TarteaucitronConsentChecker()->hasConsent(self::request(['tarteaucitron' => ['x' => '1']])));
    }

    public function testACustomCookieName(): void
    {
        $request = self::request(['tac' => '!device_view=true', 'tarteaucitron' => '!device_view=false']);

        self::assertTrue(new TarteaucitronConsentChecker(cookie: 'tac')->hasConsent($request));
        self::assertFalse(new TarteaucitronConsentChecker()->hasConsent($request));
    }

    public function testACustomKey(): void
    {
        $request = self::request(['tarteaucitron' => '!dv_pref=true!device_view=false']);

        self::assertTrue(new TarteaucitronConsentChecker(key: 'dv_pref')->hasConsent($request));
        self::assertFalse(new TarteaucitronConsentChecker()->hasConsent($request));
    }

    public function testNullStandsForTheDefaults(): void
    {
        $request = self::request(['tarteaucitron' => '!device_view=true']);

        self::assertTrue(new TarteaucitronConsentChecker(null, null)->hasConsent($request));
    }

    public function testAKeyWithRegexCharactersIsMatchedLiterally(): void
    {
        $checker = new TarteaucitronConsentChecker(key: 'a.b');

        self::assertTrue($checker->hasConsent(self::request(['tarteaucitron' => '!a.b=true'])));
        self::assertNull($checker->hasConsent(self::request(['tarteaucitron' => '!axb=true'])));
    }

    /**
     * @param array<string, mixed> $cookies
     */
    private static function request(array $cookies): Request
    {
        return Request::create('http://localhost/view', cookies: $cookies);
    }
}
