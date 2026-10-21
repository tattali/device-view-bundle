<?php

declare(strict_types=1);

namespace DeviceViewBundle\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Event\BeforeRedirectEvent;
use DeviceViewBundle\Redirect\RedirectAction;
use DeviceViewBundle\Redirect\RedirectPolicy;
use DeviceViewBundle\Redirect\RedirectReason;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\Routing\RouteDefaults;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Sends a main request to the host of its view, on kernel.request at priority 30, after the
 * switch (31).
 *
 * Nothing happens for a sub-request, a skipped request, a method that is not in
 * redirect.methods, a route whose redirect route default is none, a request that carries a view
 * served on this host in the switch parameter, or when no view has its redirect enabled: the view
 * then stays unresolved until something asks for it. Otherwise the listener resolves the view,
 * and the redirect policy decides whether the request goes to the host of that view, with the
 * action the route default sets, if any. BeforeRedirectEvent, with the reason device, may then
 * change the URL or the status, or cancel the redirect. The redirect gets redirect.cache_control.
 */
final readonly class DeviceRedirectListener
{
    /**
     * @param list<string>                  $methods         the request methods the device redirects apply to, upper case
     * @param string|null                   $cacheControl    the Cache-Control of the redirect, or null to leave it alone
     * @param SwitchParameter|null          $switchParameter the switch parameter, which tells the view a redirect of the bundle carried here
     * @param RouteDefaults|null            $routeDefaults   the reader of the route defaults; without it, routes override nothing
     * @param EventDispatcherInterface|null $dispatcher      dispatches BeforeRedirectEvent; without it, no event
     */
    public function __construct(
        private RedirectPolicy $policy,
        private ViewResolverInterface $resolver,
        private array $methods = ['GET', 'HEAD'],
        private ?string $cacheControl = 'no-store, private',
        private ?SwitchParameter $switchParameter = null,
        private ?RouteDefaults $routeDefaults = null,
        private ?EventDispatcherInterface $dispatcher = null,
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

        // the route defaults are in the request attributes: this listener runs after the router
        $routeAction = null === $this->routeDefaults ? null : $context->routeRedirect($this->routeDefaults, $request);
        if (RedirectAction::None === $routeAction) {
            return;
        }

        // a request that carries, in the switch parameter, a view served on this host was sent here
        // by a redirect of the bundle: it stays, even when the switch does not apply to it
        // (switch.enabled false) and the view resolves to another host, which would send it back
        $carried = $this->switchParameter?->carriedView($request);
        if (null !== $carried && $this->policy->servesHere($request, $carried, $routeAction)) {
            return;
        }

        $redirect = $this->policy->deviceRedirect($request, $context->resolve($this->resolver, $request)->view, $routeAction);
        if (null === $redirect) {
            return;
        }

        $beforeRedirect = new BeforeRedirectEvent(RedirectReason::Device, $redirect->url, $redirect->status);
        $this->dispatcher?->dispatch($beforeRedirect);
        if ($beforeRedirect->isCancelled()) {
            return;
        }

        $response = new RedirectResponse($beforeRedirect->getUrl(), $beforeRedirect->getStatus());
        if (null !== $this->cacheControl) {
            $response->headers->set('Cache-Control', $this->cacheControl);
        }

        $event->setResponse($response);
    }
}
