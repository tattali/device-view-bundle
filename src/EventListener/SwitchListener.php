<?php

declare(strict_types=1);

namespace DeviceViewBundle\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Redirect\RedirectPolicy;
use DeviceViewBundle\Remember\RememberPolicy;
use DeviceViewBundle\Remember\RememberTrigger;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Applies the switch query parameter, on kernel.request at priority 31, after the RouterListener
 * (32).
 *
 * When the main request carries a valid switch parameter, the listener records the switch on
 * the context, which plans to remember its view, and, unless switch.target is none, answers with
 * a redirect to the switch target: the current page without the parameter, or the home page.
 * When the view of the request is served on another host (device_view.redirect.<view>), the
 * redirect goes to that host instead, as the redirect policy decides. The redirect gets
 * redirect.cache_control. A switch that is not remembered, on the host its view is served on,
 * gets no redirect. The view itself comes from the resolver, whose switch step reads the same
 * parameter.
 */
final readonly class SwitchListener
{
    /**
     * @param int                 $status         the redirect status: 302, 303 or 307
     * @param string|null         $cacheControl   the Cache-Control of the redirect, or null to leave it alone
     * @param RedirectPolicy|null $redirectPolicy the redirect policy of the views served on their own host, used with $resolver; without them the switch stays on the current host
     * @param RememberPolicy|null $rememberPolicy whether a switch is remembered; without it, it is
     */
    public function __construct(
        private SwitchParameter $parameter,
        private SwitchUrlGenerator $urls,
        private SwitchTarget $target = SwitchTarget::Current,
        private int $status = 302,
        private ?string $cacheControl = 'no-store, private',
        private ?RedirectPolicy $redirectPolicy = null,
        private ?ViewResolverInterface $resolver = null,
        private ?RememberPolicy $rememberPolicy = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        if (!$context instanceof DeviceViewContext) {
            return;
        }

        $view = $context->readSwitch($this->parameter, $request);
        if (null === $view) {
            return;
        }

        $context->markSwitched($view);
        if (SwitchTarget::None === $this->target) {
            return;
        }

        $location = $this->location($request, $context);
        if (null === $location) {
            return;
        }

        // BeforeRedirectEvent, with the reason "switch", is dispatched here once it exists: it may
        // change the URL or the status, or cancel the redirect and keep the switch
        $response = new RedirectResponse($location, $this->status);
        if (null !== $this->cacheControl) {
            $response->headers->set('Cache-Control', $this->cacheControl);
        }

        $event->setResponse($response);
    }

    /**
     * The host of the view of the request when it is served on another host, else the switch
     * target on the current host; null when the switch stays without a redirect.
     */
    private function location(Request $request, DeviceViewContext $context): ?string
    {
        if (null === $this->redirectPolicy || null === $this->resolver || !$this->redirectPolicy->hasRedirects()) {
            return $this->urls->redirectUrl($request, $this->target);
        }

        $view = $context->resolve($this->resolver, $request)->view;
        $url = $this->redirectPolicy->switchUrl($request, $view, $this->target);
        if (null !== $url) {
            return $url;
        }

        // A switch that is not remembered (remember.on_switch false) and arrives on the host of its
        // view stays on the page that carries it: the redirect to the page without the parameter
        // would forget the view, and the device redirect could then send the request back to the
        // host it came from, in a loop. The route default and BeforeRememberEvent, which can also
        // stop the remember, are checked here once they exist.
        if (false === $this->rememberPolicy?->remembers(RememberTrigger::Switch) && $this->redirectPolicy->servesHere($request, $view)) {
            return null;
        }

        return $this->urls->redirectUrl($request, $this->target);
    }
}
