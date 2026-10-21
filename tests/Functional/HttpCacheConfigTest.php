<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The configuration of http_cache.
 */
final class HttpCacheConfigTest extends TestCase
{
    use HandlesRequests;

    /**
     * @return iterable<string, array{string}>
     */
    public static function keys(): iterable
    {
        yield 'vary' => ['vary'];
        yield 'private_when_remembering' => ['private_when_remembering'];
    }

    #[DataProvider('keys')]
    #[Group('CFG-62')]
    public function testTheKeysAreBooleans(string $key): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('Invalid type for path "device_view.http_cache.%s"', $key));

        $this->boot(['http_cache' => [$key => 'yes']]);
    }

    public function testTheDefaultsAddVary(): void
    {
        $this->boot([]);

        self::assertSame(['Cookie', 'User-Agent'], $this->handle(self::request('/view', 'iphone'))->getVary());
    }
}
