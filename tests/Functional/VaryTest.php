<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\EventListener\RememberListener;
use DeviceViewBundle\EventListener\VaryListener;
use DeviceViewBundle\Tests\App\ResponseRecorder;
use DeviceViewBundle\Tests\App\TestController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The Vary header of the main response (http_cache.vary), through the kernel: it lists the
 * request inputs the view resolution consulted, after the values the response already has.
 */
final class VaryTest extends TestCase
{
    use HandlesRequests;

    #[Group('VARY-01')]
    public function testNoVaryWhenTheViewIsNeverUsed(): void
    {
        $response = $this->handle(self::request('/', 'iphone'));

        self::assertSame('home page', $response->getContent());
        self::assertFalse($response->headers->has('Vary'));
    }

    #[Group('VARY-02')]
    public function testTheDetectorDecided(): void
    {
        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    public function testTheInputsAreOneHeaderLine(): void
    {
        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame(['Cookie, User-Agent'], $response->headers->all('vary'));
    }

    #[Group('VARY-03')]
    public function testTheRememberedViewDecided(): void
    {
        $response = $this->handle(self::request('/view', cookies: ['device_view' => 'tablet']));

        self::assertSame('tablet remembered', $response->getContent());
        self::assertSame(['Cookie'], $response->getVary());
    }

    #[Group('VARY-04')]
    public function testTheSwitchDecided(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);

        $response = $this->handle(self::request('/view?device_view=tablet'));

        self::assertSame('tablet switch', $response->getContent());
        self::assertFalse($response->headers->has('Vary'));
    }

    #[Group('VARY-06')]
    public function testTheInputsAreAppendedToTheValuesOfTheController(): void
    {
        $request = self::request('/view', 'iphone');
        $request->attributes->set(TestController::VARY_ATTRIBUTE, 'Accept-Language');

        $response = $this->handle($request);

        self::assertSame(['Accept-Language', 'Cookie', 'User-Agent'], $response->getVary());
        self::assertSame(['Accept-Language', 'Cookie, User-Agent'], $response->headers->all('vary'), 'the value of the controller is kept as it is');
    }

    #[Group('VARY-07')]
    public function testNoDuplicates(): void
    {
        $request = self::request('/view', 'iphone');
        $request->attributes->set(TestController::VARY_ATTRIBUTE, 'user-agent');

        $response = $this->handle($request);

        self::assertSame(['user-agent', 'Cookie'], $response->getVary());
    }

    public function testNothingIsAddedWhenTheControllerListsEveryInput(): void
    {
        $request = self::request('/view', 'iphone');
        $request->attributes->set(TestController::VARY_ATTRIBUTE, 'COOKIE, User-Agent');

        $response = $this->handle($request);

        self::assertSame(['COOKIE, User-Agent'], $response->headers->all('vary'));
    }

    #[Group('VARY-08')]
    public function testVaryFalse(): void
    {
        $this->boot(['http_cache' => ['vary' => false]]);

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame('mobile detector', $response->getContent());
        self::assertFalse($response->headers->has('Vary'));
    }

    #[Group('VARY-10')]
    public function testStorageNoneDoesNotVaryOnCookie(): void
    {
        $this->boot(['remember' => ['storage' => 'none']]);

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['User-Agent'], $response->getVary());
    }

    #[Group('VARY-11')]
    public function testAForcedViewVariesOnNothing(): void
    {
        $response = $this->handle(self::request('/set-view/mobile'));

        self::assertSame('mobile forced', $response->getContent());
        self::assertFalse($response->headers->has('Vary'));
    }

    #[Group('VARY-12')]
    public function testSubRequestResponsesGetNoVary(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/with-sub-request', 'iphone'));

        self::assertSame('main=mobile sub=mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
        $recorder = $this->service(ResponseRecorder::class);
        self::assertInstanceOf(ResponseRecorder::class, $recorder);
        self::assertCount(1, $recorder->subResponses);
        self::assertFalse($recorder->subResponses[0]->headers->has('Vary'));
    }

    public function testTheVaryListenerRunsBeforeTheRememberListener(): void
    {
        $this->boot();

        self::assertSame([[VaryListener::class, 0], [RememberListener::class, 0]], $this->responseListeners());
    }

    public function testNoVaryListenerWithVaryFalse(): void
    {
        $this->boot(['http_cache' => ['vary' => false]]);

        self::assertSame([[RememberListener::class, 0]], $this->responseListeners());
    }

    /**
     * The bundle's kernel.response listeners, in the order they run, with their priority.
     *
     * @return list<array{class-string, int|null}>
     */
    private function responseListeners(): array
    {
        $dispatcher = $this->service('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $listeners = [];
        foreach ($dispatcher->getListeners(KernelEvents::RESPONSE) as $listener) {
            if (\is_array($listener) && ($listener[0] instanceof VaryListener || $listener[0] instanceof RememberListener)) {
                $listeners[] = [$listener[0]::class, $dispatcher->getListenerPriority(KernelEvents::RESPONSE, $listener)];
            }
        }

        return $listeners;
    }
}
