<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The configuration of skip.
 */
final class SkipConfigTest extends TestCase
{
    use HandlesRequests;

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'unclosed group' => ['^/(api'];
        yield 'unmatched closing brace' => ['^/api}x'];
        yield 'nothing to repeat' => ['*api'];
    }

    #[DataProvider('invalidPaths')]
    #[Group('CFG-43')]
    public function testSkipPathsAreRegularExpressions(string $path): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('device_view.skip.paths contains an invalid regular expression: "%s".', $path));

        $this->boot(['skip' => ['paths' => ['^/valid', $path]]]);
    }

    #[Group('CFG-64')]
    public function testSkipFragmentsIsABoolean(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.skip.fragments"');

        $this->boot(['skip' => ['fragments' => 'yes']]);
    }

    #[Group('CFG-64')]
    public function testSkipPathsAreStrings(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.skip.paths.0"');

        $this->boot(['skip' => ['paths' => [['^/api']]]]);
    }

    public function testRegularExpressionsWithBracesAreValid(): void
    {
        $this->boot(['skip' => ['paths' => ['^/a{1}pi/']]]);

        self::assertSame(302, $this->handle(self::request('/view?device_view=mobile'))->getStatusCode());
        self::assertSame(200, $this->handle(self::request('/api/thing?device_view=mobile'))->getStatusCode());
    }

    public function testNoSkipPaths(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com']], 'skip' => ['paths' => []]]);

        $response = $this->handle(self::request('http://www.example.com/_profiler/abc', 'iphone'));

        self::assertSame('https://m.example.com/_profiler/abc?device_view=mobile', $response->headers->get('Location'));
    }
}
