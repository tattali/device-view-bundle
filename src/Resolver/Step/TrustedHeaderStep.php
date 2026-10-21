<?php

declare(strict_types=1);

namespace DeviceViewBundle\Resolver\Step;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The view a proxy or CDN tells in the trusted header (device_view.trusted_header.name).
 *
 * With trusted_proxies_only, the header is read only from a trusted proxy
 * (Request::isFromTrustedProxy()): the header of any other client is not read, and so not
 * consulted. Once read, it counts as consulted even when it is absent or invalid, since the
 * answer depends on it. A value that is not a configured view name is ignored and logged at
 * debug level; the request holds it, so there is nothing to expire.
 *
 * The step is registered only when trusted_header.name is set.
 *
 * @internal
 */
final readonly class TrustedHeaderStep implements ResolutionStepInterface
{
    /**
     * @param string $name               the header name, as configured
     * @param bool   $trustedProxiesOnly whether only a trusted proxy may send it
     */
    public function __construct(
        private string $name,
        private bool $trustedProxiesOnly,
        private ViewNames $names,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public function source(): ViewSource
    {
        return ViewSource::TrustedHeader;
    }

    #[\Override]
    public function find(Request $request, DeviceViewContext $context): StepResult
    {
        if ($this->trustedProxiesOnly && !$request->isFromTrustedProxy()) {
            return new StepResult();
        }

        $value = $request->headers->get($this->name);
        $consulted = [$this->name];
        if (null === $value) {
            return new StepResult(null, $consulted);
        }

        $view = $this->names->parse($value);
        if (null === $view) {
            $this->logger?->debug('Ignored an invalid device view from the trusted header.');
        }

        return new StepResult($view, $consulted);
    }
}
