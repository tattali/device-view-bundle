<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\DeviceView;
use DeviceViewBundle\Resolver\Step\DetectorStep;
use DeviceViewBundle\Resolver\Step\ForcedStep;
use DeviceViewBundle\Resolver\ViewResolver;
use DeviceViewBundle\Tests\App\Detector\HeaderDetector;
use DeviceViewBundle\Tests\App\TestLogger;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DeviceViewTest extends TestCase
{
    public function testItResolvesTheViewOfTheMainRequestContext(): void
    {
        $stack = new RequestStack();
        $context = new DeviceViewContext();
        $stack->push(self::mainRequest($context, 'tablet'));

        $deviceView = self::deviceView($stack);

        self::assertSame(DeviceType::Tablet, $deviceView->getView());
        self::assertSame(ViewSource::Detector, $deviceView->getSource());
        self::assertSame('tablet', $deviceView->getViewName());
        self::assertTrue($deviceView->isTablet());
        self::assertFalse($deviceView->isMobile());
        self::assertFalse($deviceView->isDesktop());
        self::assertTrue($deviceView->is(DeviceType::Tablet));
        self::assertFalse($deviceView->is(DeviceType::Mobile));
        self::assertTrue($deviceView->is('tablet'));
        self::assertSame(DeviceType::Tablet, $context->resolved?->view);
    }

    public function testASubRequestReadsTheMainRequestContext(): void
    {
        $stack = new RequestStack();
        $stack->push(self::mainRequest(new DeviceViewContext(), 'mobile'));
        $stack->push(Request::create('/sub'));

        self::assertSame(DeviceType::Mobile, self::deviceView($stack)->getView());
    }

    public function testSetViewForcesTheViewOfTheRequest(): void
    {
        $stack = new RequestStack();
        $context = new DeviceViewContext();
        $stack->push(self::mainRequest($context, 'mobile'));
        $deviceView = self::deviceView($stack);
        self::assertSame(DeviceType::Mobile, $deviceView->getView());

        $deviceView->setView(DeviceType::Desktop);

        self::assertSame(DeviceType::Desktop, $context->forcedView);
        self::assertSame(DeviceType::Desktop, $deviceView->getView());
        self::assertSame(ViewSource::Forced, $deviceView->getSource());
        self::assertSame(['X-Test-View'], $context->consultedInputs);
    }

    public function testSetViewTakesAConfiguredName(): void
    {
        $stack = new RequestStack();
        $context = new DeviceViewContext();
        $stack->push(self::mainRequest($context, null));

        self::deviceView($stack, new ViewNames(['mobile' => 'm']))->setView('m');

        self::assertSame(DeviceType::Mobile, $context->forcedView);
    }

    public function testWithoutARequestTheDefaultViewApplies(): void
    {
        $deviceView = self::deviceView(new RequestStack(), defaultView: DeviceType::Mobile);

        self::assertSame(DeviceType::Mobile, $deviceView->getView());
        self::assertSame(ViewSource::Default, $deviceView->getSource());
        self::assertTrue($deviceView->isMobile());
    }

    public function testAMainRequestWithoutContextGetsTheDefaultView(): void
    {
        $stack = new RequestStack();
        $request = Request::create('/');
        $request->headers->set('X-Test-View', 'tablet');
        $stack->push($request);
        $request->attributes->set('_device_view', 'not a context');
        $logger = new TestLogger();
        $deviceView = self::deviceView($stack, logger: $logger);

        self::assertSame(DeviceType::Desktop, $deviceView->getView());
        self::assertSame(ViewSource::Default, $deviceView->getSource());

        $deviceView->setView('mobile');

        self::assertSame('not a context', $request->attributes->get('_device_view'));
        self::assertSame(['Ignored DeviceView::setView(): there is no request.'], $logger->messages(LogLevel::DEBUG));
    }

    public function testSetViewWithoutARequestOrLoggerIsIgnored(): void
    {
        $deviceView = self::deviceView(new RequestStack());

        $deviceView->setView(DeviceType::Mobile);

        self::assertSame(DeviceType::Desktop, $deviceView->getView());
    }

    private static function deviceView(RequestStack $stack, ViewNames $names = new ViewNames(), DeviceType $defaultView = DeviceType::Desktop, ?TestLogger $logger = null): DeviceView
    {
        $resolver = new ViewResolver([new ForcedStep(), new DetectorStep(new HeaderDetector())]);

        return new DeviceView($stack, $resolver, $names, $defaultView, $logger);
    }

    private static function mainRequest(DeviceViewContext $context, ?string $view): Request
    {
        $request = Request::create('/');
        $request->attributes->set('_device_view', $context);
        if (null !== $view) {
            $request->headers->set('X-Test-View', $view);
        }

        return $request;
    }
}
