<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Resolver;

use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\TestCase;

final class ResolvedViewTest extends TestCase
{
    public function testItHoldsTheViewItsSourceAndTheConsultedInputs(): void
    {
        $resolved = new ResolvedView(DeviceType::Tablet, ViewSource::Detector, ['User-Agent']);

        self::assertSame(DeviceType::Tablet, $resolved->view);
        self::assertSame(ViewSource::Detector, $resolved->source);
        self::assertSame(['User-Agent'], $resolved->consultedInputs);
    }

    public function testNothingConsultedByDefault(): void
    {
        self::assertSame([], new ResolvedView(DeviceType::Desktop, ViewSource::Default)->consultedInputs);
    }
}
