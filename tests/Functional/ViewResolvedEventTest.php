<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\DeviceView;
use DeviceViewBundle\Event\ViewResolvedEvent;
use DeviceViewBundle\Tests\App\ControllerCall;
use DeviceViewBundle\Tests\App\Detector\CountingDetector;
use DeviceViewBundle\Tests\App\Event\ViewResolvedListener;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * ViewResolvedEvent through the kernel: the test application listens to it with
 * ViewResolvedListener.
 */
final class ViewResolvedEventTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    #[Group('EVT-RES-01')]
    public function testAListenerChangesTheViewAndTheSourceStays(): void
    {
        $this->boot();
        $this->listener()->onEvent = static function (ViewResolvedEvent $event): void {
            $event->setView(DeviceType::Tablet);
        };

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    #[Group('EVT-RES-02')]
    public function testGetters(): void
    {
        $this->boot();
        $request = self::request('/view', 'iphone');

        $this->handle($request);

        $events = $this->listener()->events;
        self::assertCount(1, $events);
        self::assertSame(DeviceType::Mobile, $events[0]['view']);
        self::assertSame(ViewSource::Detector, $events[0]['source']);
        self::assertSame($request, $events[0]['request'], 'the main request');
    }

    #[Group('EVT-RES-03')]
    public function testDispatchedOncePerMainRequest(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/with-sub-request', 'iphone'));

        self::assertSame('main=mobile sub=mobile detector', $response->getContent());
        self::assertCount(1, $this->listener()->events);
    }

    #[Group('EVT-RES-04')]
    public function testNotDispatchedWhenTheViewIsNotUsed(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/', 'iphone'));

        self::assertSame('home page', $response->getContent());
        self::assertSame([], $this->listener()->events);
    }

    #[Group('EVT-RES-05')]
    public function testDispatchedAfterTabletAsMobile(): void
    {
        $this->boot(['tablet_as_mobile' => true]);

        $this->handle(self::request('/view', 'ipad_legacy'));

        $events = $this->listener()->events;
        self::assertCount(1, $events);
        self::assertSame(DeviceType::Mobile, $events[0]['view']);
        self::assertSame(ViewSource::Detector, $events[0]['source']);
    }

    #[Group('EVT-RES-06')]
    public function testTheListenerViewDrivesTheRedirect(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com']]]);
        $this->listener()->onEvent = static function (ViewResolvedEvent $event): void {
            $event->setView(DeviceType::Mobile);
        };

        $response = $this->handle(self::request('http://www.example.com/', 'desktop_chrome'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://m.example.com/?device_view=mobile', $response->headers->get('Location'));
    }

    public function testTheListenerViewIsTheRememberedDetectedView(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);
        $this->listener()->onEvent = static function (ViewResolvedEvent $event): void {
            $event->setView(DeviceType::Tablet);
        };

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame('tablet detector', $response->getContent());
        self::assertSetCookie(self::sc('tablet'), $response);
    }

    #[Group('EVT-RES-07')]
    public function testTheEventNameIsTheClassName(): void
    {
        $this->boot();
        $dispatcher = $this->service('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $this->handle(self::request('/view'));

        self::assertTrue($dispatcher->hasListeners(ViewResolvedEvent::class));
        self::assertCount(1, $this->listener()->events, 'the listener on ViewResolvedEvent::class is called');
    }

    #[Group('EVT-RES-08')]
    public function testAViewForcedAfterTheEventIsNotChangedAgain(): void
    {
        $this->boot();
        $this->listener()->onEvent = static function (ViewResolvedEvent $event): void {
            $event->setView(DeviceType::Desktop);
        };
        $controllerCall = $this->service(ControllerCall::class);
        self::assertInstanceOf(ControllerCall::class, $controllerCall);
        $controllerCall->calls = static function (DeviceView $deviceView): array {
            $before = $deviceView->getViewName();
            $deviceView->setView('tablet');

            return [$before, $deviceView->getViewName(), $deviceView->getSource()->value];
        };

        $response = $this->handle(self::request('/view', 'iphone'), false);

        self::assertSame(['desktop', 'tablet', ViewSource::Forced->value], $controllerCall->results);
        self::assertSame('tablet forced', $response->getContent());
        self::assertCount(1, $this->listener()->events);
    }

    #[Group('EVT-RES-09')]
    public function testAViewForcedBeforeTheFirstReadIsDispatched(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/set-view/tablet', 'iphone'));

        self::assertSame('tablet forced', $response->getContent());
        $events = $this->listener()->events;
        self::assertCount(1, $events);
        self::assertSame(DeviceType::Tablet, $events[0]['view']);
        self::assertSame(ViewSource::Forced, $events[0]['source']);
    }

    #[Group('EVT-RES-10')]
    public function testAListenerReadingTheViewDoesNotResolveAgain(): void
    {
        $this->boot(['detector' => ['id' => 'app.counting_detector']], ['app.counting_detector' => CountingDetector::class]);
        $deviceView = $this->deviceView();
        /** @var \ArrayObject<int, string> $reads */
        $reads = new \ArrayObject();
        $this->listener()->onEvent = static function (ViewResolvedEvent $event) use ($deviceView, $reads): void {
            $reads->append($deviceView->getViewName());
            $event->setView(DeviceType::Tablet);
            $reads->append($deviceView->getViewName().' '.$deviceView->getSource()->value);
        };

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame(['mobile', 'tablet detector'], $reads->getArrayCopy());
        self::assertSame('tablet detector', $response->getContent());
        $detector = $this->service('app.counting_detector');
        self::assertInstanceOf(CountingDetector::class, $detector);
        self::assertSame(1, $detector->calls);
        self::assertCount(1, $this->listener()->events);
    }

    #[Group('EVT-RES-11')]
    public function testAViewForcedInAListenerWins(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);
        $deviceView = $this->deviceView();
        /** @var \ArrayObject<int, string> $reads */
        $reads = new \ArrayObject();
        $this->listener()->onEvent = static function (ViewResolvedEvent $event) use ($deviceView, $reads): void {
            $deviceView->setView('desktop', true);
            $reads->append($deviceView->getViewName().' '.$deviceView->getSource()->value);
            $event->setView(DeviceType::Tablet);
        };

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame(['desktop forced'], $reads->getArrayCopy());
        self::assertSame('desktop forced', $response->getContent());
        self::assertSetCookie(self::sc('desktop'), $response);
        self::assertCount(1, $this->listener()->events);
    }

    private function deviceView(): DeviceView
    {
        $deviceView = $this->service('test.device_view');
        self::assertInstanceOf(DeviceView::class, $deviceView);

        return $deviceView;
    }

    private function listener(): ViewResolvedListener
    {
        $listener = $this->service(ViewResolvedListener::class);
        self::assertInstanceOf(ViewResolvedListener::class, $listener);

        return $listener;
    }
}
