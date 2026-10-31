<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use PHPUnit\Framework\TestCase;

/**
 * composer.json: Twig, TwigBundle and WebProfilerBundle are optional. The bundle does not
 * require them, suggests them, and keeps them as dev dependencies so the tests cover them.
 */
final class OptionalBundlesTest extends TestCase
{
    private const array OPTIONAL = ['twig/twig', 'symfony/twig-bundle', 'symfony/web-profiler-bundle'];

    public function testTheOptionalPackagesAreNotRequired(): void
    {
        $composer = self::composer();

        foreach (self::OPTIONAL as $package) {
            self::assertArrayNotHasKey($package, $composer['require'], $package);
        }
    }

    public function testTheOptionalPackagesAreSuggestedWithAReason(): void
    {
        $composer = self::composer();

        foreach (self::OPTIONAL as $package) {
            self::assertArrayHasKey($package, $composer['suggest'], $package);
            self::assertNotSame('', trim($composer['suggest'][$package]), $package);
        }
    }

    public function testTheOptionalPackagesAreDevDependencies(): void
    {
        $composer = self::composer();

        foreach (self::OPTIONAL as $package) {
            self::assertArrayHasKey($package, $composer['require-dev'], $package);
        }
    }

    /**
     * @return array{require: array<string, string>, 'require-dev': array<string, string>, suggest: array<string, string>}
     */
    private static function composer(): array
    {
        /** @var array{require: array<string, string>, 'require-dev': array<string, string>, suggest: array<string, string>} $composer */
        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);

        return $composer;
    }
}
