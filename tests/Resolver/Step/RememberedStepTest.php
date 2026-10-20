<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Remember\CookieStorage;
use DeviceViewBundle\Resolver\Step\RememberedStep;
use DeviceViewBundle\Tests\App\Remember\ArrayStorage;
use DeviceViewBundle\Tests\App\TestLogger;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

final class RememberedStepTest extends TestCase
{
    private const string INVALID = 'Ignored an invalid device view from the remember storage.';

    public function testItsSourceIsRemembered(): void
    {
        self::assertSame(ViewSource::Remembered, new RememberedStep(new CookieStorage(), new ViewNames())->source());
    }

    public function testNothingStored(): void
    {
        $logger = new TestLogger();
        $context = new DeviceViewContext();

        $result = new RememberedStep(new CookieStorage(), new ViewNames(), $logger)->find(Request::create('/'), $context);

        self::assertNull($result->view);
        self::assertSame(['Cookie'], $result->consultedInputs);
        self::assertFalse($context->forgedStoredValue);
        self::assertSame([], $logger->records);
    }

    public function testAStoredViewIsParsed(): void
    {
        $logger = new TestLogger();
        $context = new DeviceViewContext();
        $step = new RememberedStep(new CookieStorage(), new ViewNames(['tablet' => 't']), $logger);

        $result = $step->find(Request::create('/', cookies: ['device_view' => ' T ']), $context);

        self::assertSame(DeviceType::Tablet, $result->view);
        self::assertSame(['Cookie'], $result->consultedInputs);
        self::assertFalse($context->forgedStoredValue);
        self::assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forgedValues(): iterable
    {
        yield 'unknown word' => ['phone'];
        yield 'empty' => [''];
        yield 'not_mobile' => ['not_mobile'];
    }

    #[DataProvider('forgedValues')]
    public function testAForgedValueIsLoggedAndMarked(string $value): void
    {
        $logger = new TestLogger();
        $context = new DeviceViewContext();

        $result = new RememberedStep(new CookieStorage(), new ViewNames(), $logger)->find(Request::create('/', cookies: ['device_view' => $value]), $context);

        self::assertNull($result->view);
        self::assertSame(['Cookie'], $result->consultedInputs);
        self::assertTrue($context->forgedStoredValue);
        self::assertSame([self::INVALID], $logger->messages(LogLevel::DEBUG));
        self::assertCount(1, $logger->records);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonStringValues(): iterable
    {
        yield 'array' => [['a' => 'mobile']];
        yield 'integer' => [3];
        yield 'boolean' => [true];
    }

    /**
     * Whether the storage can remove the value is up to its clear().
     */
    #[DataProvider('nonStringValues')]
    public function testAnyOtherValueIsLoggedAndMarked(mixed $value): void
    {
        $logger = new TestLogger();
        $context = new DeviceViewContext();
        $storage = new ArrayStorage();
        $storage->value = $value;

        $result = new RememberedStep($storage, new ViewNames(), $logger)->find(Request::create('/'), $context);

        self::assertNull($result->view);
        self::assertTrue($context->forgedStoredValue);
        self::assertSame([self::INVALID], $logger->messages(LogLevel::DEBUG));
    }

    public function testWithoutALogger(): void
    {
        $context = new DeviceViewContext();

        self::assertNull(new RememberedStep(new CookieStorage(), new ViewNames())->find(Request::create('/', cookies: ['device_view' => 'phone']), $context)->view);
        self::assertTrue($context->forgedStoredValue);
    }
}
