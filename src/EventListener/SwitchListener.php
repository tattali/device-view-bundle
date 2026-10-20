<?php

declare(strict_types=1);

namespace DeviceViewBundle\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Redirect\RedirectPolicy;
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
 * redirect.cache_control. The view itself comes from the resolver, whose switch step reads the
 * same parameter.
 */
final readonly class SwitchListener
{
    /**
     * @param int                 $status         the redirect status: 302, 303 or 307
     * @param string|null         $cacheControl   the Cache-Control of the redirect, or null to leave it alone
     * @param RedirectPolicy|null $redirectPolicy the redirect policy of the views served on their own host, used with $resolver; without them the switch stays on the current host
     */
    public function __construct(
        private SwitchParameter $parameter,
        private SwitchUrlGenerator $urls,
        private SwitchTarget $target = SwitchTarget::Current,
        private int $status = 302,
        private ?string $cacheControl = 'no-store, private',
        private ?RedirectPolicy $redirectPolicy = null,
        private ?ViewResolverInterface $resolver = null,
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

        // BeforeRedirectEvent, with the reason "switch", is dispatched here once it exists: it may
        // change the URL or the status, or cancel the redirect and keep the switch
        $response = new RedirectResponse($this->location($request, $context), $this->status);
        if (null !== $this->cacheControl) {
            $response->headers->set('Cache-Control', $this->cacheControl);
        }

        $event->setResponse($response);
    }

    /**
     * The host of the view of the request when it is served on another host, else the switch
     * target on the current host.
     */
    private function location(Request $request, DeviceViewContext $context): string
    {
        $url = null;
        if (null !== $this->redirectPolicy && null !== $this->resolver && $this->redirectPolicy->hasRedirects()) {
            $url = $this->redirectPolicy->switchUrl($request, $context->resolve($this->resolver, $request)->view, $this->target);
        }

        return $url ?? $this->urls->redirectUrl($request, $this->target);
    }
}
