<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Event;

use DeviceViewBundle\Event\ViewResolvedEvent;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

final class ViewResolvedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $request = Request::create('/');

        $event = new ViewResolvedEvent(DeviceType::Mobile, ViewSource::Detector, $request);

        self::assertSame(DeviceType::Mobile, $event->getView());
        self::assertSame(ViewSource::Detector, $event->getSource());
        self::assertSame($request, $event->getRequest());
    }

    public function testSetViewChangesTheViewOnly(): void
    {
        $request = Request::create('/');
        $event = new ViewResolvedEvent(DeviceType::Mobile, ViewSource::Remembered, $request);

        $event->setView(DeviceType::Tablet);

        self::assertSame(DeviceType::Tablet, $event->getView());
        self::assertSame(ViewSource::Remembered, $event->getSource());
        self::assertSame($request, $event->getRequest());
    }

    public function testItIsAFinalContractsEvent(): void
    {
        $event = new ViewResolvedEvent(DeviceType::Desktop, ViewSource::Default, Request::create('/'));

        self::assertSame([Event::class => Event::class], class_parents($event));
        self::assertTrue(new \ReflectionClass(ViewResolvedEvent::class)->isFinal());
        self::assertFalse($event->isPropagationStopped());
    }
}
