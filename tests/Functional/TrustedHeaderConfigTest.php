<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The configuration of trusted_header.
 */
final class TrustedHeaderConfigTest extends TestCase
{
    use HandlesRequests;

    public function testTheHeaderIsNotReadByDefault(): void
    {
        $this->boot([], framework: ['trusted_proxies' => '127.0.0.1']);
        $request = self::request('/view', 'iphone');
        $request->headers->set('X-Device-View', 'tablet');

        self::assertSame('mobile detector', $this->handle($request)->getContent());
    }

    public function testANullNameReadsNoHeader(): void
    {
        $this->boot(['trusted_header' => ['name' => null, 'trusted_proxies_only' => false]]);
        $request = self::request('/view', 'iphone');
        $request->headers->set('X-Device-View', 'tablet');

        $response = $this->handle($request);

        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validNames(): iterable
    {
        yield 'dashes' => ['X-Device-View'];
        yield 'every token character' => ["A0!#$%&'*+-.^_`|~z"];
    }

    #[DataProvider('validNames')]
    public function testAValidName(string $name): void
    {
        $this->boot(['trusted_header' => ['name' => $name, 'trusted_proxies_only' => false]]);
        $request = self::request('/view', 'iphone');
        $request->headers->set($name, 'tablet');

        $response = $this->handle($request);

        self::assertSame('tablet trusted_header', $response->getContent());
        self::assertSame(['Cookie', $name], $response->getVary());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'space' => ['X Device'];
        yield 'empty' => [''];
        yield 'colon' => ['X-Device:'];
        yield 'line break' => ["X-Device\n"];
        yield 'comma' => ['X-Device,Cookie'];
        yield 'non-ASCII' => ['X-Dévice'];
    }

    #[DataProvider('invalidNames')]
    #[Group('CFG-44')]
    public function testTheNameIsAHeaderName(string $name): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('device_view.trusted_header.name "%s" is not a valid header name.', $name));

        $this->boot(['trusted_header' => ['name' => $name]]);
    }

    #[Group('CFG-44')]
    public function testTheNameIsAString(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.trusted_header.name must be null or a header name, got 5.');

        $this->boot(['trusted_header' => ['name' => 5]]);
    }

    #[Group('CFG-63')]
    public function testTrustedProxiesOnlyIsABoolean(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.trusted_header.trusted_proxies_only"');

        $this->boot(['trusted_header' => ['name' => 'X-Device-View', 'trusted_proxies_only' => 'yes']]);
    }
}
