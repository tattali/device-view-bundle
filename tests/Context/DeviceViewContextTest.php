<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Context;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Tests\App\Resolver\RecordingResolver;
use DeviceViewBundle\Tests\App\TestLogger;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DeviceViewContextTest extends TestCase
{
    public function testANewContextHasResolvedNothing(): void
    {
        $context = new DeviceViewContext();

        self::assertNull($context->resolved);
        self::assertNull($context->forcedView);
        self::assertSame([], $context->consultedInputs);
        self::assertFalse($context->skipped);
        self::assertNull($context->switchView);
        self::assertFalse($context->switched);
        self::assertNull($context->remember);
        self::assertNull($context->plannedView);
        self::assertFalse($context->forgedStoredValue);
        self::assertFalse($context->responseBuilt);
    }

    public function testTheSwitchParameterIsReadOnce(): void
    {
        $context = new DeviceViewContext();
        $logger = new TestLogger();
        $parameter = new SwitchParameter(new ViewNames(), logger: $logger);
        $request = Request::create('/?device_view=phone');

        self::assertNull($context->readSwitch($parameter, $request));
        self::assertNull($context->readSwitch($parameter, Request::create('/?device_view=mobile')));
        self::assertNull($context->switchView);
        self::assertCount(1, $logger->records, 'an invalid token is logged once per request');
    }

    public function testAValidSwitchIsKept(): void
    {
        $context = new DeviceViewContext();
        $parameter = new SwitchParameter(new ViewNames());

        self::assertSame(DeviceType::Tablet, $context->readSwitch($parameter, Request::create('/?device_view=tablet')));
        self::assertSame(DeviceType::Tablet, $context->readSwitch($parameter, Request::create('/')));
        self::assertSame(DeviceType::Tablet, $context->switchView);
        self::assertFalse($context->switched, 'reading the parameter does not apply the switch');
    }

    public function testMarkingTheSwitchRecordsItAndPlansItsView(): void
    {
        $context = new DeviceViewContext();

        $context->markSwitched(DeviceType::Tablet);

        self::assertTrue($context->switched);
        self::assertSame(DeviceType::Tablet, $context->plannedView);
    }

    public function testTheViewIsResolvedOnce(): void
    {
        $context = new DeviceViewContext();
        $request = Request::create('/');
        $resolver = new RecordingResolver([new ResolvedView(DeviceType::Mobile, ViewSource::Detector, ['Cookie', 'User-Agent'])]);

        $first = $context->resolve($resolver, $request);
        $second = $context->resolve($resolver, $request);

        self::assertSame(DeviceType::Mobile, $first->view);
        self::assertSame($first, $second);
        self::assertSame($first, $context->resolved);
        self::assertSame([[$request, $context]], $resolver->calls);
        self::assertSame(['Cookie', 'User-Agent'], $context->consultedInputs);
    }

    public function testForcingAViewResolvesAgainAndKeepsTheInputsAlreadyConsulted(): void
    {
        $context = new DeviceViewContext();
        $request = Request::create('/');
        $resolver = new RecordingResolver([
            new ResolvedView(DeviceType::Mobile, ViewSource::Detector, ['Cookie', 'User-Agent']),
            new ResolvedView(DeviceType::Tablet, ViewSource::Forced, ['User-Agent', 'X-Other']),
        ]);
        $context->resolve($resolver, $request);

        $context->force(DeviceType::Tablet);

        self::assertSame(DeviceType::Tablet, $context->forcedView);
        self::assertNull($context->resolved);
        $resolved = $context->resolve($resolver, $request);
        self::assertSame(DeviceType::Tablet, $resolved->view);
        self::assertSame(ViewSource::Forced, $resolved->source);
        self::assertCount(2, $resolver->calls);
        self::assertSame(['Cookie', 'User-Agent', 'X-Other'], $context->consultedInputs);
    }

    public function testForcingBeforeAnyReadOnlyRecordsTheView(): void
    {
        $context = new DeviceViewContext();

        $context->force(DeviceType::Desktop);

        self::assertSame(DeviceType::Desktop, $context->forcedView);
        self::assertNull($context->resolved);
        self::assertSame([], $context->consultedInputs);
    }

    public function testForcingWithRememberPlansTheView(): void
    {
        $context = new DeviceViewContext();
        $context->markSwitched(DeviceType::Mobile);

        $context->force(DeviceType::Tablet, true);

        self::assertSame(DeviceType::Tablet, $context->forcedView);
        self::assertSame(DeviceType::Tablet, $context->plannedView, 'it replaces the view the switch planned');

        $context->force(DeviceType::Desktop);

        self::assertSame(DeviceType::Desktop, $context->forcedView);
        self::assertSame(DeviceType::Tablet, $context->plannedView, 'forcing without remember keeps the plan');
    }

    public function testForcingWithoutRememberPlansNothing(): void
    {
        $context = new DeviceViewContext();

        $context->force(DeviceType::Tablet);

        self::assertNull($context->plannedView);
    }

    public function testSetRememberRecordsTheOverride(): void
    {
        $context = new DeviceViewContext();

        $context->setRemember(false);
        self::assertFalse($context->remember);

        $context->setRemember(true);
        self::assertTrue($context->remember);
    }

    public function testSetConsentRecordsTheOverride(): void
    {
        $context = new DeviceViewContext();
        self::assertNull($context->consent, 'no override: the consent checker answers');

        $context->setConsent(true);
        self::assertTrue($context->consent);

        $context->setConsent(false);
        self::assertFalse($context->consent);

        $context->setConsent(null);
        self::assertNull($context->consent);
    }

    public function testMarkingAForgedStoredValueRecordsIt(): void
    {
        $context = new DeviceViewContext();

        $context->markForgedStoredValue();

        self::assertTrue($context->forgedStoredValue);
    }

    public function testMarkingTheResponseBuiltRecordsIt(): void
    {
        $context = new DeviceViewContext();

        $context->markResponseBuilt();

        self::assertTrue($context->responseBuilt);
    }
}
