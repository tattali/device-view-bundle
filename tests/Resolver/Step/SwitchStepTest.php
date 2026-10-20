<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\Step\SwitchStep;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SwitchStepTest extends TestCase
{
    public function testItGivesTheViewOfTheSwitchParameterAndConsultsNoHeader(): void
    {
        $step = new SwitchStep(new SwitchParameter(new ViewNames()));
        $context = new DeviceViewContext();

        $result = $step->find(Request::create('/?device_view=tablet'), $context);

        self::assertSame(DeviceType::Tablet, $result->view);
        self::assertSame([], $result->consultedInputs);
        self::assertSame(DeviceType::Tablet, $context->switchView, 'read through the context, once per request');
        self::assertSame(ViewSource::Switch, $step->source());
    }

    public function testWithoutAValidSwitchItHasNoAnswer(): void
    {
        $step = new SwitchStep(new SwitchParameter(new ViewNames()));

        self::assertNull($step->find(Request::create('/'), new DeviceViewContext())->view);
        self::assertNull($step->find(Request::create('/?device_view=phone'), new DeviceViewContext())->view);
    }
}
