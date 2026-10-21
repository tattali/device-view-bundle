<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Event;

use DeviceViewBundle\Event\BeforeRememberEvent;
use DeviceViewBundle\Remember\RememberTrigger;
use DeviceViewBundle\View\DeviceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

final class BeforeRememberEventTest extends TestCase
{
    public function testGetters(): void
    {
        $event = new BeforeRememberEvent(RememberTrigger::Detected, DeviceType::Tablet, true, 31536000);

        self::assertSame(RememberTrigger::Detected, $event->getTrigger());
        self::assertSame(DeviceType::Tablet, $event->getView());
        self::assertTrue($event->getConsent());
        self::assertSame(31536000, $event->getLifetime());
        self::assertFalse($event->isCancelled());
    }

    /**
     * @return iterable<string, array{bool|null}>
     */
    public static function consents(): iterable
    {
        yield 'granted' => [true];
        yield 'denied' => [false];
        yield 'unknown' => [null];
    }

    #[DataProvider('consents')]
    public function testTheConsentIsGivenAsIs(?bool $consent): void
    {
        self::assertSame($consent, new BeforeRememberEvent(RememberTrigger::Switch, DeviceType::Mobile, $consent, 0)->getConsent());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function lifetimes(): iterable
    {
        yield 'browser session' => [0];
        yield 'one second' => [1];
        yield 'an hour' => [3600];
        yield 'two years' => [63072000];
    }

    #[DataProvider('lifetimes')]
    public function testSetLifetimeChangesTheLifetimeOnly(int $lifetime): void
    {
        $event = new BeforeRememberEvent(RememberTrigger::Switch, DeviceType::Mobile, null, 60);

        $event->setLifetime($lifetime);

        self::assertSame($lifetime, $event->getLifetime());
        self::assertSame(RememberTrigger::Switch, $event->getTrigger());
        self::assertSame(DeviceType::Mobile, $event->getView());
        self::assertNull($event->getConsent());
        self::assertFalse($event->isCancelled());
        self::assertSame($lifetime, new BeforeRememberEvent(RememberTrigger::Detected, DeviceType::Desktop, null, $lifetime)->getLifetime());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function negativeLifetimes(): iterable
    {
        yield '-1' => [-1];
        yield '-3600' => [-3600];
        yield 'PHP_INT_MIN' => [\PHP_INT_MIN];
    }

    #[DataProvider('negativeLifetimes')]
    public function testSetLifetimeRejectsANegativeLifetime(int $lifetime): void
    {
        $event = new BeforeRememberEvent(RememberTrigger::Switch, DeviceType::Mobile, null, 60);

        try {
            $event->setLifetime($lifetime);
            self::fail('A negative lifetime is rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(\sprintf('The remember lifetime must be 0 or more seconds; got %d.', $lifetime), $exception->getMessage());
        }

        self::assertSame(60, $event->getLifetime(), 'the lifetime is unchanged');
    }

    #[DataProvider('negativeLifetimes')]
    public function testTheConstructorRejectsANegativeLifetime(int $lifetime): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The remember lifetime must be 0 or more seconds; got %d.', $lifetime));

        new BeforeRememberEvent(RememberTrigger::Switch, DeviceType::Mobile, null, $lifetime);
    }

    public function testCancel(): void
    {
        $event = new BeforeRememberEvent(RememberTrigger::Switch, DeviceType::Mobile, null, 0);

        $event->cancel();

        self::assertTrue($event->isCancelled());
        self::assertFalse($event->isPropagationStopped(), 'later listeners still see the event');
        $event->cancel();
        self::assertTrue($event->isCancelled(), 'cancelling twice keeps it cancelled');
        self::assertSame(0, $event->getLifetime());
    }

    public function testItIsAFinalContractsEvent(): void
    {
        $event = new BeforeRememberEvent(RememberTrigger::Switch, DeviceType::Mobile, null, 0);

        self::assertSame([Event::class => Event::class], class_parents($event));
        self::assertTrue(new \ReflectionClass(BeforeRememberEvent::class)->isFinal());
        self::assertFalse($event->isPropagationStopped());
    }
}
