<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\View;

use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewNamesTest extends TestCase
{
    public function testTheDefaultNamesAreTheCanonicalNames(): void
    {
        $names = new ViewNames();

        self::assertSame(['mobile', 'tablet', 'desktop'], $names->names());
        self::assertSame('mobile', $names->nameOf(DeviceType::Mobile));
        self::assertSame('tablet', $names->nameOf(DeviceType::Tablet));
        self::assertSame('desktop', $names->nameOf(DeviceType::Desktop));
    }

    public function testRenamingOneViewKeepsTheOthers(): void
    {
        $names = new ViewNames(['tablet' => 't']);

        self::assertSame(['mobile', 't', 'desktop'], $names->names());
        self::assertSame('t', $names->nameOf(DeviceType::Tablet));
        self::assertSame(DeviceType::Tablet, $names->parse('t'));
        self::assertSame(DeviceType::Mobile, $names->parse('mobile'));
    }

    /**
     * @return iterable<string, array{mixed, DeviceType|null}>
     */
    public static function tokens(): iterable
    {
        yield 'mobile' => ['mobile', DeviceType::Mobile];
        yield 'tablet' => ['tablet', DeviceType::Tablet];
        yield 'desktop' => ['desktop', DeviceType::Desktop];
        yield 'trimmed and lower-cased' => [" \tMobile\n ", DeviceType::Mobile];
        yield 'upper case' => ['TABLET', DeviceType::Tablet];
        yield 'unknown word' => ['phone', null];
        yield 'not_mobile' => ['not_mobile', null];
        yield 'empty' => ['', null];
        yield 'blank' => ['   ', null];
        yield 'array' => [['mobile'], null];
        yield 'keyed array' => [['a' => 'mobile'], null];
        yield 'null' => [null, null];
        yield 'integer' => [1, null];
        yield 'boolean' => [true, null];
        yield 'enum case' => [DeviceType::Mobile, null];
        yield 'garbage bytes' => ["\0\xFF", null];
    }

    #[DataProvider('tokens')]
    public function testParseAcceptsOnlyAConfiguredNameInAString(mixed $token, ?DeviceType $expected): void
    {
        self::assertSame($expected, new ViewNames()->parse($token));
    }

    public function testParseAcceptsOnlyTheConfiguredNames(): void
    {
        $names = new ViewNames(['mobile' => 'm', 'tablet' => 't', 'desktop' => 'd']);

        self::assertSame(DeviceType::Mobile, $names->parse('m'));
        self::assertSame(DeviceType::Tablet, $names->parse(' T '));
        self::assertSame(DeviceType::Desktop, $names->parse('d'));
        self::assertNull($names->parse('mobile'));
        self::assertNull($names->parse('desktop'));
    }

    public function testANumericNameIsAName(): void
    {
        $names = new ViewNames(['mobile' => '1']);

        self::assertSame(DeviceType::Mobile, $names->parse('1'));
        self::assertSame('1', $names->nameOf(DeviceType::Mobile));
        self::assertSame(['1', 'tablet', 'desktop'], $names->names());
        self::assertNull($names->parse(1));
    }

    public function testFromNameReturnsTheViewOfAConfiguredName(): void
    {
        $names = new ViewNames(['mobile' => 'm']);

        self::assertSame(DeviceType::Mobile, $names->fromName('m'));
        self::assertSame(DeviceType::Tablet, $names->fromName('tablet'));
        self::assertSame(DeviceType::Desktop, $names->fromName('desktop'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownNames(): iterable
    {
        yield 'unknown word' => ['phone'];
        yield 'not trimmed' => [' mobile'];
        yield 'not lower-cased' => ['Mobile'];
        yield 'empty' => [''];
    }

    #[DataProvider('unknownNames')]
    public function testFromNameRejectsAnythingElse(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown device view "'.$name.'". Expected one of: mobile, tablet, desktop.');

        new ViewNames()->fromName($name);
    }

    public function testTheErrorListsTheConfiguredNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown device view "mobile". Expected one of: m, t, d.');

        new ViewNames(['mobile' => 'm', 'tablet' => 't', 'desktop' => 'd'])->fromName('mobile');
    }
}
