<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Resolver\ViewResolver;
use DeviceViewBundle\Tests\App\Resolver\TabletResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The configuration of the resolution: default_view, tablet_as_mobile, views.names and
 * services.resolver.
 */
final class ResolutionConfigTest extends TestCase
{
    use HandlesRequests;

    public function testTheDefaultsAreDesktopWithoutTabletMapping(): void
    {
        $this->boot([]);

        self::assertSame('desktop default', $this->handle(self::request('/view', 'empty'))->getContent());
        self::assertSame('tablet detector', $this->handle(self::request('/view', 'ipad_legacy'))->getContent());
    }

    #[Group('CFG-02')]
    public function testDefaultViewIsAView(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "phone" is not allowed for path "device_view.default_view"');

        $this->boot(['default_view' => 'phone']);
    }

    #[Group('CFG-03')]
    public function testTabletAsMobileIsABoolean(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "device_view.tablet_as_mobile"');

        $this->boot(['tablet_as_mobile' => 'yes']);
    }

    #[Group('CFG-04')]
    public function testViewNamesAreUnique(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The view names must be unique: "m" is used twice.');

        $this->boot(['views' => ['names' => ['mobile' => 'm', 'tablet' => 'm']]]);
    }

    public function testARenamedViewCannotTakeTheNameOfAnother(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The view names must be unique: "desktop" is used twice.');

        $this->boot(['views' => ['names' => ['mobile' => 'desktop']]]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'upper case and punctuation' => ['Mobile!'];
        yield 'space' => ['m v'];
        yield 'empty' => [''];
        yield 'trailing new line' => ["m\n"];
        yield 'accented letter' => ['é'];
    }

    #[Group('CFG-05')]
    #[DataProvider('invalidNames')]
    public function testViewNamePattern(string $name): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The view name "'.$name.'" is invalid: use lowercase letters, digits, "_" and "-" only.');

        $this->boot(['views' => ['names' => ['mobile' => $name]]]);
    }

    public function testEveryViewNameIsChecked(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The view name "Desk" is invalid');

        $this->boot(['views' => ['names' => ['desktop' => 'Desk']]]);
    }

    public function testViewNamesTakeLowercaseLettersDigitsUnderscoresAndDashes(): void
    {
        $this->boot(['views' => ['names' => ['mobile' => 'm_0-9', 'tablet' => 'az', 'desktop' => '7']]]);

        self::assertSame('m_0-9 detector', $this->handle(self::request('/view', 'iphone'))->getContent());
        self::assertSame('az detector', $this->handle(self::request('/view', 'ipad_legacy'))->getContent());
        self::assertSame('7 detector', $this->handle(self::request('/view', 'desktop_chrome'))->getContent());
    }

    #[Group('CFG-06')]
    public function testUnknownViewKey(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "phone" under "device_view.views.names"');

        $this->boot(['views' => ['names' => ['phone' => 'p']]]);
    }

    public function testTheBuiltInResolverIsTheDefault(): void
    {
        $this->boot([]);

        self::assertInstanceOf(ViewResolver::class, $this->service('test.device_view.resolver'));
        self::assertSame($this->service('test.device_view.resolver'), $this->service('test.view_resolver_interface'));
    }

    #[Group('SVC-02')]
    public function testServicesResolver(): void
    {
        $this->boot(['services' => ['resolver' => 'app.tablet_resolver']], ['app.tablet_resolver' => TabletResolver::class]);

        self::assertSame('tablet default', $this->handle(self::request('/view', 'iphone'))->getContent());
        self::assertSame($this->service('app.tablet_resolver'), $this->service('test.device_view.resolver'));
        self::assertSame($this->service('app.tablet_resolver'), $this->service('test.view_resolver_interface'));
    }

    #[Group('CFG-49')]
    public function testServicesResolverMustBeAResolver(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.not_a_resolver" set in device_view.services.resolver must implement DeviceViewBundle\Resolver\ViewResolverInterface.');

        $this->boot(['services' => ['resolver' => 'app.not_a_resolver']], ['app.not_a_resolver' => \stdClass::class]);
    }

    public function testServicesResolverMustExist(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.nope" set in device_view.services.resolver does not exist.');

        $this->boot(['services' => ['resolver' => 'app.nope']]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidResolverIds(): iterable
    {
        yield 'empty string' => ['', '""'];
        yield 'integer' => [12, '12'];
        yield 'boolean' => [true, 'true'];
    }

    #[DataProvider('invalidResolverIds')]
    public function testServicesResolverIsNullOrAServiceId(mixed $id, string $shown): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.services.resolver must be null or a service id, got '.$shown.'.');

        $this->boot(['services' => ['resolver' => $id]]);
    }

    public function testServicesResolverCanBeNull(): void
    {
        $this->boot(['services' => ['resolver' => null]]);

        self::assertInstanceOf(ViewResolver::class, $this->service('test.device_view.resolver'));
    }
}
