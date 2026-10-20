<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * The view the device detector gives. The detector's headers count as read even when it cannot
 * tell, since its answer depends on them.
 *
 * @internal
 */
final readonly class DetectorStep implements ResolutionStepInterface
{
    public function __construct(
        private DeviceDetectorInterface $detector,
    ) {
    }

    #[\Override]
    public function source(): ViewSource
    {
        return ViewSource::Detector;
    }

    #[\Override]
    public function find(Request $request, DeviceViewContext $context): StepResult
    {
        return new StepResult($this->detector->detect($request), $this->detector->varyHeaders());
    }
}
