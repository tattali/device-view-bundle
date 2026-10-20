<?php

declare(strict_types=1);

namespace DeviceViewBundle\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Redirect\RedirectPolicy;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Sends a main request to the host of its view, on kernel.request at priority 30, after the
 * switch (31).
 *
 * Nothing happens for a sub-request, a skipped request, a method that is not in
 * redirect.methods, or when no view has its redirect enabled: the view then stays unresolved
 * until something asks for it. Otherwise the listener resolves the view, and the redirect
 * policy decides whether the request goes to the host of that view. The redirect gets
 * redirect.cache_control.
 */
final readonly class DeviceRedirectListener
{
    /**
     * @param list<string> $methods      the request methods the device redirects apply to, upper case
     * @param string|null  $cacheControl the Cache-Control of the redirect, or null to leave it alone
     */
    public function __construct(
        private RedirectPolicy $policy,
        private ViewResolverInterface $resolver,
        private array $methods = ['GET', 'HEAD'],
        private ?string $cacheControl = 'no-store, private',
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->policy->hasRedirects()) {
            return;
        }

        $request = $event->getRequest();
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        // a skipped request (the fragment path, skip.paths) is never redirected
        if (!$context instanceof DeviceViewContext || $context->skipped || !\in_array($request->getMethod(), $this->methods, true)) {
            return;
        }

        $redirect = $this->policy->deviceRedirect($request, $context->resolve($this->resolver, $request)->view);
        if (null === $redirect) {
            return;
        }

        // BeforeRedirectEvent, with the reason "device", is dispatched here once it exists: it
        // may change the URL or the status, or cancel the redirect
        $response = new RedirectResponse($redirect->url, $redirect->status);
        if (null !== $this->cacheControl) {
            $response->headers->set('Cache-Control', $this->cacheControl);
        }

        $event->setResponse($response);
    }
}
