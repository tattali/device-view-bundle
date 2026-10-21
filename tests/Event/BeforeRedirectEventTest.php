<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Event;

use DeviceViewBundle\Event\BeforeRedirectEvent;
use DeviceViewBundle\Redirect\RedirectReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

final class BeforeRedirectEventTest extends TestCase
{
    public function testGetters(): void
    {
        $event = new BeforeRedirectEvent(RedirectReason::Device, 'https://m.example.com/?device_view=mobile', 301);

        self::assertSame(RedirectReason::Device, $event->getReason());
        self::assertSame('https://m.example.com/?device_view=mobile', $event->getUrl());
        self::assertSame(301, $event->getStatus());
        self::assertFalse($event->isCancelled());
    }

    #[DataProvider('reasons')]
    public function testTheReasonValues(string $value, RedirectReason $reason): void
    {
        self::assertSame($reason, RedirectReason::tryFrom($value));
    }

    /**
     * @return iterable<string, array{string, RedirectReason}>
     */
    public static function reasons(): iterable
    {
        yield 'switch' => ['switch', RedirectReason::Switch];
        yield 'device' => ['device', RedirectReason::Device];
    }

    public function testSetUrlChangesTheUrlOnly(): void
    {
        $event = new BeforeRedirectEvent(RedirectReason::Switch, '/view', 302);

        $event->setUrl('/elsewhere');

        self::assertSame('/elsewhere', $event->getUrl());
        self::assertSame(RedirectReason::Switch, $event->getReason());
        self::assertSame(302, $event->getStatus());
        self::assertFalse($event->isCancelled());
    }

    #[DataProvider('statuses')]
    public function testSetStatusAcceptsEveryRedirectStatus(int $status): void
    {
        $event = new BeforeRedirectEvent(RedirectReason::Switch, '/view', 302);

        $event->setStatus($status);

        self::assertSame($status, $event->getStatus());
        self::assertSame('/view', $event->getUrl());
        self::assertSame($status, new BeforeRedirectEvent(RedirectReason::Device, '/', $status)->getStatus());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function statuses(): iterable
    {
        foreach ([301, 302, 303, 307, 308] as $status) {
            yield (string) $status => [$status];
        }
    }

    #[DataProvider('invalidStatuses')]
    public function testSetStatusRejectsAnyOtherStatus(int $status): void
    {
        $event = new BeforeRedirectEvent(RedirectReason::Switch, '/view', 302);

        try {
            $event->setStatus($status);
            self::fail('An invalid status is rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(\sprintf('The redirect status must be one of 301, 302, 303, 307, 308; got %d.', $status), $exception->getMessage());
        }

        self::assertSame(302, $event->getStatus(), 'the status is unchanged');
    }

    #[DataProvider('invalidStatuses')]
    public function testTheConstructorRejectsAnyOtherStatus(int $status): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The redirect status must be one of 301, 302, 303, 307, 308; got %d.', $status));

        new BeforeRedirectEvent(RedirectReason::Device, '/', $status);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidStatuses(): iterable
    {
        foreach ([200, 300, 304, 305, 306, 309, 0, -302] as $status) {
            yield (string) $status => [$status];
        }
    }

    public function testCancel(): void
    {
        $event = new BeforeRedirectEvent(RedirectReason::Switch, '/view', 302);

        $event->cancel();

        self::assertTrue($event->isCancelled());
        self::assertFalse($event->isPropagationStopped(), 'later listeners still see the event');
        $event->cancel();
        self::assertTrue($event->isCancelled(), 'cancelling twice keeps it cancelled');
    }

    public function testItIsAFinalContractsEvent(): void
    {
        $event = new BeforeRedirectEvent(RedirectReason::Switch, '/', 302);

        self::assertSame([Event::class => Event::class], class_parents($event));
        self::assertTrue(new \ReflectionClass(BeforeRedirectEvent::class)->isFinal());
        self::assertFalse($event->isPropagationStopped());
    }
}
