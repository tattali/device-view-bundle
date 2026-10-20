<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Tests\App\Consent\HeaderConsent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The configuration of the consent checker: consent.*.
 */
final class ConsentConfigTest extends TestCase
{
    use HandlesRequests;

    #[Group('CFG-30')]
    public function testStorageNoneCannotHaveAProvider(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.consent.provider cannot be set when device_view.remember.storage is "none".');

        $this->boot(['remember' => ['storage' => 'none'], 'consent' => ['provider' => 'tarteaucitron']]);
    }

    public function testStorageNoneWithoutProviderBuilds(): void
    {
        $container = $this->boot(['remember' => ['storage' => 'none']]);

        self::assertFalse($container->has('test.device_view.consent_checker'));
    }

    #[Group('CFG-31')]
    public function testTheProviderServiceImplementsTheInterface(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.not_a_checker" set in device_view.consent.provider must implement DeviceViewBundle\Consent\ConsentCheckerInterface.');

        $this->boot(['consent' => ['provider' => 'app.not_a_checker']], ['app.not_a_checker' => \stdClass::class]);
    }

    public function testAnUnknownProviderIsAMissingService(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "cookie_tool" set in device_view.consent.provider does not exist.');

        $this->boot(['consent' => ['provider' => 'cookie_tool']]);
    }

    public function testTheProviderCannotBeTheAliasItSets(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.consent.provider cannot be "device_view.consent_checker", the alias it sets: set it to the id of your own service.');

        $this->boot(['consent' => ['provider' => 'device_view.consent_checker']]);
    }

    public function testAReaderServiceIdIsAServiceLikeAnother(): void
    {
        $this->boot(['consent' => ['provider' => 'app.header_consent']], ['app.header_consent' => HeaderConsent::class]);

        self::assertInstanceOf(HeaderConsent::class, $this->service('test.device_view.consent_checker'));
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty provider' => ['provider', '', 'device_view.consent.provider must be null, the name of a consent reader or a service id, got "".'];
        yield 'provider not a string' => ['provider', 3, 'device_view.consent.provider must be null, the name of a consent reader or a service id, got 3.'];
        yield 'key not a string' => ['key', true, 'device_view.consent.key must be null or a non-empty string, got true.'];
        yield 'cookie not a string' => ['cookie', 1.5, 'device_view.consent.cookie must be null or a non-empty string, got 1.5.'];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValues(string $key, mixed $value, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->boot(['consent' => ['provider' => 'tarteaucitron', $key => $value]]);
    }

    #[Group('CFG-32')]
    public function testTheKeyIsNotEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.consent.key must be null or a non-empty string, got "".');

        $this->boot(['consent' => ['provider' => 'tarteaucitron', 'key' => '']]);
    }

    #[Group('CFG-53')]
    public function testTheCookieIsNotEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.consent.cookie must be null or a non-empty string, got "".');

        $this->boot(['consent' => ['provider' => 'tarteaucitron', 'cookie' => '']]);
    }

    public function testNullKeyAndCookieStandForTheReaderDefaults(): void
    {
        $container = $this->boot(['consent' => ['provider' => 'tarteaucitron', 'key' => null, 'cookie' => null]]);

        self::assertTrue($container->has('test.device_view.consent_checker'));
    }
}
