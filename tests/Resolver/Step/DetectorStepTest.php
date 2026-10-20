<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Resolver\Step\DetectorStep;
use DeviceViewBundle\Tests\App\Detector\HeaderDetector;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DetectorStepTest extends TestCase
{
    public function testItGivesTheDetectedViewAndConsultsTheDetectorHeaders(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-Test-View', 'tablet');

        $result = new DetectorStep(new HeaderDetector())->find($request, new DeviceViewContext());

        self::assertSame(DeviceType::Tablet, $result->view);
        self::assertSame(['X-Test-View'], $result->consultedInputs);
        self::assertSame(ViewSource::Detector, new DetectorStep(new HeaderDetector())->source());
    }

    public function testWhenTheDetectorCannotTellTheHeadersWereStillConsulted(): void
    {
        $result = new DetectorStep(new HeaderDetector())->find(Request::create('/'), new DeviceViewContext());

        self::assertNull($result->view);
        self::assertSame(['X-Test-View'], $result->consultedInputs);
    }
}
