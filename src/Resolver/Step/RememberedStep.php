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
 * A stored value that is not a configured view name is ignored and logged at debug level. When
 * it is a string, the context records it as forged, so the response expires it. Any other value
 * (an array, from a cookie named device_view[a]) is only ignored: expiring the storage would not
 * remove it.
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
            if (\is_string($stored)) {
                $context->markForgedStoredValue();
            }
        }

        return new StepResult($view, $consulted);
    }
}
