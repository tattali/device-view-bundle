<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Remember\ViewStorageInterface;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The view the remember storage holds. The storage's inputs count as read even when it holds
 * nothing, since the answer depends on them.
 *
 * A stored value that is not a configured view name is ignored and logged at debug level, and
 * the context records it as forged, so the response removes it. Whether the storage can remove
 * it is up to the storage: the cookie storage leaves an array (a cookie named device_view[a])
 * alone, the session storage removes any value.
 *
 * @internal
 */
final readonly class RememberedStep implements ResolutionStepInterface
{
    public function __construct(
        private ViewStorageInterface $storage,
        private ViewNames $names,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public function source(): ViewSource
    {
        return ViewSource::Remembered;
    }

    #[\Override]
    public function find(Request $request, DeviceViewContext $context): StepResult
    {
        $stored = $this->storage->read($request);
        $consulted = $this->storage->varyHeaders();
        if (null === $stored) {
            return new StepResult(null, $consulted);
        }

        $view = $this->names->parse($stored);
        if (null === $view) {
            $this->logger?->debug('Ignored an invalid device view from the remember storage.');
            $context->markForgedStoredValue();
        }

        return new StepResult($view, $consulted);
    }
}
