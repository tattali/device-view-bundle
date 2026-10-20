<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Consent;

use DeviceViewBundle\Consent\ComplianzConsentChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Complianz reader on its own: the fixtures of tests/Fixtures/consent/complianz and the edge
 * cases of its parser. ComplianzTest checks the CON-CMP rows through the kernel.
 */
final class ComplianzConsentCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool|null}>
     */
    public static function fixtures(): iterable
    {
        return ConsentFixtures::of('complianz');
    }

    /**
     * @param array<string, mixed> $cookies
     */
    #[DataProvider('fixtures')]
    public function testFixtures(array $cookies, ?bool $expected): void
    {
        self::assertSame($expected, new ComplianzConsentChecker()->hasConsent(self::request($cookies)));
    }

    /**
     * @return iterable<string, array{mixed, bool|null}>
     */
    public static function values(): iterable
    {
        yield 'allow' => ['allow', true];
        yield 'deny' => ['deny', false];
        yield 'upper-case allow' => ['Allow', null];
        yield 'upper-case deny' => ['DENY', null];
        yield 'allow with a trailing space' => ['allow ', null];
        yield 'deny with a leading space' => [' deny', null];
        yield 'allow with a suffix' => ['allowed', null];
        yield 'a boolean word' => ['true', null];
        yield 'a digit' => ['1', null];
        yield 'empty' => ['', null];
        yield 'a URL-encoded value left as is' => ['%61llow', null];
        yield 'invalid UTF-8' => ["allow\xFF", null];
        yield 'a NUL byte' => ["allow\0", null];
        yield 'oversized' => [str_repeat('allow', 1000), null];
        yield 'an array' => [['a' => 'allow'], null];
        yield 'a list' => [['allow'], null];
        yield 'an integer' => [1, null];
        yield 'a boolean' => [true, null];
    }

    #[DataProvider('values')]
    public function testValues(mixed $value, ?bool $expected): void
    {
        self::assertSame($expected, new ComplianzConsentChecker()->hasConsent(self::request(['cmplz_preferences' => $value])));
    }

    public function testNoCookie(): void
    {
        self::assertNull(new ComplianzConsentChecker()->hasConsent(self::request([])));
    }

    public function testOnlyTheCookieOfTheKeyDecides(): void
    {
        $request = self::request(['cmplz_statistics' => 'allow', 'cmplz_marketing' => 'allow', 'cmplz_functional' => 'allow']);

        self::assertNull(new ComplianzConsentChecker()->hasConsent($request));
    }

    public function testThePrefixIsNotAPattern(): void
    {
        $request = self::request(['xcmplz_preferences' => 'allow', 'cmplz_preferencesx' => 'allow', 'cmplzpreferences' => 'allow']);

        self::assertNull(new ComplianzConsentChecker()->hasConsent($request));
    }

    public function testACustomPrefix(): void
    {
        $request = self::request(['cmplz_rt_preferences' => 'allow', 'cmplz_preferences' => 'deny']);

        self::assertTrue(new ComplianzConsentChecker(cookie: 'cmplz_rt_')->hasConsent($request));
        self::assertFalse(new ComplianzConsentChecker()->hasConsent($request));
    }

    public function testACustomKey(): void
    {
        $request = self::request(['cmplz_statistics' => 'deny', 'cmplz_preferences' => 'allow']);

        self::assertFalse(new ComplianzConsentChecker(key: 'statistics')->hasConsent($request));
        self::assertTrue(new ComplianzConsentChecker()->hasConsent($request));
    }

    public function testTheFunctionalCategoryIsReadLikeAnother(): void
    {
        $checker = new ComplianzConsentChecker(key: 'functional');

        self::assertTrue($checker->hasConsent(self::request(['cmplz_functional' => 'allow'])));
        self::assertNull($checker->hasConsent(self::request([])));
    }

    public function testNullStandsForTheDefaults(): void
    {
        $request = self::request(['cmplz_preferences' => 'allow']);

        self::assertTrue(new ComplianzConsentChecker(null, null)->hasConsent($request));
    }

    /**
     * @param array<string, mixed> $cookies
     */
    private static function request(array $cookies): Request
    {
        return Request::create('http://localhost/view', cookies: $cookies);
    }
}
