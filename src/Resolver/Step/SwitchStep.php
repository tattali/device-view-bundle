<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\View\ViewSource;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use Symfony\Component\HttpFoundation\Request;

/**
 * The view of the switch query parameter, so the view a switch asks for is the view of the
 * request, also for route conditions that read it before the switch listener runs. It reads no
 * request header: the parameter is part of the URL.
 *
 * @internal
 */
final readonly class SwitchStep implements ResolutionStepInterface
{
    public function __construct(
        private SwitchParameter $parameter,
    ) {
    }

    #[\Override]
    public function source(): ViewSource
    {
        return ViewSource::Switch;
    }

    #[\Override]
    public function find(Request $request, DeviceViewContext $context): StepResult
    {
        return new StepResult($context->readSwitch($this->parameter, $request));
    }
}
