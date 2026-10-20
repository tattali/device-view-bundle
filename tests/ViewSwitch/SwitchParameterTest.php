<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\ViewSwitch;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Tests\App\TestLogger;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

final class SwitchParameterTest extends TestCase
{
    private const string INVALID = 'Ignored an invalid device view from the switch parameter.';

    public function testItReadsAValidToken(): void
    {
        $logger = new TestLogger();
        $parameter = new SwitchParameter(new ViewNames(), logger: $logger);

        self::assertSame(DeviceType::Mobile, $parameter->read(Request::create('/?device_view=mobile'), new DeviceViewContext()));
        self::assertSame(DeviceType::Tablet, $parameter->read(Request::create('/?device_view=%20TABLET%20'), new DeviceViewContext()));
        self::assertSame(DeviceType::Desktop, $parameter->read(Request::create('/?device_view=desktop'), new DeviceViewContext()));
        self::assertSame([], $logger->records);
    }

    public function testWithoutTheParameterThereIsNoSwitchAndNoLog(): void
    {
        $logger = new TestLogger();

        self::assertNull(new SwitchParameter(new ViewNames(), logger: $logger)->read(Request::create('/?other=mobile'), new DeviceViewContext()));
        self::assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'unknown word' => ['device_view=phone'];
        yield 'not_mobile' => ['device_view=not_mobile'];
        yield 'array' => ['device_view[]=mobile'];
        yield 'empty' => ['device_view='];
        yield 'garbage' => ['device_view=%00%FF'];
    }

    #[DataProvider('invalidQueries')]
    public function testAnInvalidTokenIsIgnoredAndLogged(string $query): void
    {
        $logger = new TestLogger();

        self::assertNull(new SwitchParameter(new ViewNames(), logger: $logger)->read(Request::create('/?'.$query), new DeviceViewContext()));
        self::assertSame([['level' => LogLevel::DEBUG, 'message' => self::INVALID]], $logger->records);
    }

    public function testAnInvalidTokenWithoutLoggerIsIgnored(): void
    {
        self::assertNull(new SwitchParameter(new ViewNames())->read(Request::create('/?device_view=phone'), new DeviceViewContext()));
    }

    public function testTheTokenIsAConfiguredName(): void
    {
        $parameter = new SwitchParameter(new ViewNames(['mobile' => 'm']));

        self::assertSame(DeviceType::Mobile, $parameter->read(Request::create('/?device_view=m'), new DeviceViewContext()));
        self::assertNull($parameter->read(Request::create('/?device_view=mobile'), new DeviceViewContext()));
    }

    public function testItReadsTheConfiguredParameter(): void
    {
        $parameter = new SwitchParameter(new ViewNames(), 'view');

        self::assertSame(DeviceType::Tablet, $parameter->read(Request::create('/?view=tablet&device_view=mobile'), new DeviceViewContext()));
        self::assertNull($parameter->read(Request::create('/?device_view=mobile'), new DeviceViewContext()));
    }

    public function testADisabledSwitchReadsNothing(): void
    {
        $logger = new TestLogger();
        $parameter = new SwitchParameter(new ViewNames(), enabled: false, logger: $logger);

        self::assertNull($parameter->read(Request::create('/?device_view=mobile'), new DeviceViewContext()));
        self::assertNull($parameter->read(Request::create('/?device_view=phone'), new DeviceViewContext()));
        self::assertSame([], $logger->records);
    }

    public function testASkippedRequestReadsNothing(): void
    {
        $logger = new TestLogger();
        $parameter = new SwitchParameter(new ViewNames(), logger: $logger);

        self::assertNull($parameter->read(Request::create('/?device_view=mobile'), new DeviceViewContext(skipped: true)));
        self::assertNull($parameter->read(Request::create('/?device_view=phone'), new DeviceViewContext(skipped: true)));
        self::assertSame([], $logger->records);
    }

    public function testOnlyTheConfiguredMethodsAreRead(): void
    {
        $default = new SwitchParameter(new ViewNames());
        $custom = new SwitchParameter(new ViewNames(), methods: ['GET', 'POST']);

        self::assertSame(DeviceType::Mobile, $default->read(Request::create('/?device_view=mobile', 'GET'), new DeviceViewContext()));
        self::assertSame(DeviceType::Mobile, $default->read(Request::create('/?device_view=mobile', 'HEAD'), new DeviceViewContext()));
        self::assertNull($default->read(Request::create('/?device_view=mobile', 'POST'), new DeviceViewContext()));
        self::assertSame(DeviceType::Mobile, $custom->read(Request::create('/?device_view=mobile', 'POST'), new DeviceViewContext()));
        self::assertNull($custom->read(Request::create('/?device_view=mobile', 'HEAD'), new DeviceViewContext()));
    }

    public function testItReadsTheQueryNotTheBody(): void
    {
        $parameter = new SwitchParameter(new ViewNames(), methods: ['POST']);

        self::assertNull($parameter->read(Request::create('/', 'POST', ['device_view' => 'mobile']), new DeviceViewContext()));
    }
}
