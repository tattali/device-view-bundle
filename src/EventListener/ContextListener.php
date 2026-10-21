<?php

declare(strict_types=1);

namespace DeviceViewBundle\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Attaches a new DeviceViewContext to the main request, on kernel.request at priority 33,
 * before the RouterListener (32), so route conditions can read the view.
 *
 * The context is marked skipped when the decoded path info is the fragment path, or matches one
 * of the skip.paths regular expressions: such a request still resolves its view when asked, but
 * never switches, redirects, remembers nor expires a stored value. The path info is decoded with
 * rawurldecode(), as the router and the FragmentListener decode it, so an encoded path is
 * skipped like the path it routes to.
 *
 * Nothing is resolved here: the view is resolved the first time something asks for it.
 */
final readonly class ContextListener
{
    /**
     * @param string|null  $fragmentPath the fragment path to skip, or null when skip.fragments is false
     * @param list<string> $skipPaths    the regular expressions of skip.paths, without delimiters
     */
    public function __construct(
        private ?string $fragmentPath = null,
        private array $skipPaths = [],
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = rawurldecode($request->getPathInfo());
        $skipped = $path === $this->fragmentPath
            || array_any($this->skipPaths, static fn (string $pattern): bool => 1 === preg_match('{'.$pattern.'}', $path));

        $request->attributes->set(DeviceViewContext::ATTRIBUTE, new DeviceViewContext($skipped));
    }
}
