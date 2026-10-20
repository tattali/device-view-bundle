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
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
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

    public function testSetViewWithRememberRequestsAWrite(): void
    {
        $stack = new RequestStack();
        $context = new DeviceViewContext();
        $stack->push(self::mainRequest($context, null));
        $deviceView = self::deviceView($stack);

        $deviceView->setView('tablet', true);

        self::assertSame(DeviceType::Tablet, $context->forcedView);
        self::assertSame(DeviceType::Tablet, $context->plannedView);

        $deviceView->setView('mobile');

        self::assertSame(DeviceType::Mobile, $context->forcedView);
        self::assertSame(DeviceType::Tablet, $context->plannedView, 'a view forced without remember is not planned');
    }

    public function testSetRememberSetsTheOverrideOfTheMainRequest(): void
    {
        $stack = new RequestStack();
        $context = new DeviceViewContext();
        $stack->push(self::mainRequest($context, null));
        $stack->push(Request::create('/sub'));
        $deviceView = self::deviceView($stack);

        $deviceView->setRemember(false);
        self::assertFalse($context->remember);

        $deviceView->setRemember(true);
        self::assertTrue($context->remember);
    }

    public function testSetConsentSetsTheOverrideOfTheMainRequest(): void
    {
        $stack = new RequestStack();
        $context = new DeviceViewContext();
        $stack->push(self::mainRequest($context, null));
        $stack->push(Request::create('/sub'));
        $deviceView = self::deviceView($stack);

        $deviceView->setConsent(true);
        self::assertTrue($context->consent);

        $deviceView->setConsent(false);
        self::assertFalse($context->consent);

        $deviceView->setConsent(null);
        self::assertNull($context->consent);
    }

    public function testSetConsentOutsideARequest(): void
    {
        $logger = new TestLogger();

        self::deviceView(new RequestStack(), logger: $logger)->setConsent(true);
        self::deviceView(new RequestStack())->setConsent(true);

        self::assertSame(['Ignored DeviceView::setConsent(): there is no request.'], $logger->messages(LogLevel::DEBUG));
    }

    public function testSetRememberOutsideARequest(): void
    {
        $logger = new TestLogger();

        self::deviceView(new RequestStack(), logger: $logger)->setRemember(false);
        self::deviceView(new RequestStack())->setRemember(false);

        self::assertSame(['Ignored DeviceView::setRemember(): there is no request.'], $logger->messages(LogLevel::DEBUG));
    }

    public function testChangesAfterTheResponseIsBuiltAreIgnored(): void
    {
        $stack = new RequestStack();
        $context = new DeviceViewContext();
        $stack->push(self::mainRequest($context, 'tablet'));
        $logger = new TestLogger();
        $deviceView = self::deviceView($stack, logger: $logger);
        $context->markResponseBuilt();

        $deviceView->setView('mobile', true);
        $deviceView->setRemember(false);
        $deviceView->setConsent(true);

        self::assertNull($context->forcedView);
        self::assertNull($context->plannedView);
        self::assertNull($context->remember);
        self::assertNull($context->consent);
        self::assertSame(DeviceType::Tablet, $deviceView->getView(), 'reading still works');
        self::assertSame([
            'Ignored a device view change made after the response was built.',
            'Ignored a device view change made after the response was built.',
            'Ignored a device view change made after the response was built.',
        ], $logger->messages(LogLevel::DEBUG));
    }

    public function testAnUnknownNameThrowsEvenAfterTheResponseIsBuilt(): void
    {
        $stack = new RequestStack();
        $context = new DeviceViewContext();
        $stack->push(self::mainRequest($context, null));
        $context->markResponseBuilt();

        $this->expectException(\InvalidArgumentException::class);

        self::deviceView($stack)->setView('phone');
    }

    public function testChangesAfterTheResponseIsBuiltWithoutALogger(): void
    {
        $stack = new RequestStack();
        $context = new DeviceViewContext();
        $stack->push(self::mainRequest($context, null));
        $context->markResponseBuilt();

        self::deviceView($stack)->setRemember(false);

        self::assertNull($context->remember);
    }

    public function testGetSwitchUrlGivesTheConfiguredNameOfTheView(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/view?a=1'));
        $deviceView = self::deviceView($stack, new ViewNames(['mobile' => 'm', 'tablet' => 't']), switchUrls: new SwitchUrlGenerator('v'));

        self::assertSame('/view?a=1&v=m', $deviceView->getSwitchUrl(DeviceType::Mobile));
        self::assertSame('/view?a=1&v=t', $deviceView->getSwitchUrl('t', 'current'));
        self::assertSame('/?v=desktop', $deviceView->getSwitchUrl('desktop', 'home'));
    }

    public function testGetSwitchUrlWithoutARequest(): void
    {
        self::assertSame('/?device_view=tablet', self::deviceView(new RequestStack())->getSwitchUrl(DeviceType::Tablet));
    }

    public function testGetSwitchUrlRejectsATargetThatIsNotAUrl(): void
    {
        $deviceView = self::deviceView(new RequestStack());

        foreach (['none', 'back', 'Current', ''] as $target) {
            try {
                $deviceView->getSwitchUrl(DeviceType::Mobile, $target);
                self::fail(\sprintf('getSwitchUrl() accepted the target "%s".', $target));
            } catch (\InvalidArgumentException $exception) {
                self::assertSame(\sprintf('Unknown switch target "%s". Expected "current" or "home".', $target), $exception->getMessage());
            }
        }
    }

    private static function deviceView(RequestStack $stack, ViewNames $names = new ViewNames(), DeviceType $defaultView = DeviceType::Desktop, ?TestLogger $logger = null, SwitchUrlGenerator $switchUrls = new SwitchUrlGenerator()): DeviceView
    {
        $resolver = new ViewResolver([new ForcedStep(), new DetectorStep(new HeaderDetector())]);

        return new DeviceView($stack, $resolver, $names, $switchUrls, $defaultView, $logger);
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
