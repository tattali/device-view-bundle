<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\Step\ForcedStep;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ForcedStepTest extends TestCase
{
    public function testItGivesTheForcedViewAndConsultsNothing(): void
    {
        $context = new DeviceViewContext();
        $context->force(DeviceType::Tablet);

        $result = new ForcedStep()->find(Request::create('/'), $context);

        self::assertSame(DeviceType::Tablet, $result->view);
        self::assertSame([], $result->consultedInputs);
        self::assertSame(ViewSource::Forced, new ForcedStep()->source());
    }

    public function testWithoutAForcedViewItHasNoAnswer(): void
    {
        $result = new ForcedStep()->find(Request::create('/'), new DeviceViewContext());

        self::assertNull($result->view);
        self::assertSame([], $result->consultedInputs);
    }
}
