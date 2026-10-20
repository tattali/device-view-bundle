<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * One source of the resolution order of design.md section 5: forced, switch, remembered,
 * trusted header, detector. ViewResolver asks the steps in that order; the first view wins.
 *
 * @internal
 */
interface ResolutionStepInterface
{
    /**
     * The source recorded when this step decides.
     */
    public function source(): ViewSource;

    public function find(Request $request, DeviceViewContext $context): StepResult;
}
