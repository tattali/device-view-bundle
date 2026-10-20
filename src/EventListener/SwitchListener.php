<?php

declare(strict_types=1);

namespace DeviceViewBundle\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Applies the switch query parameter, on kernel.request at priority 31, after the RouterListener
 * (32).
 *
 * When the main request carries a valid switch parameter, the listener records the switch on
 * the context, which plans to remember its view, and, unless switch.target is none, answers with
 * a redirect to the switch target: the current page without the parameter, or the home page.
 * The redirect gets redirect.cache_control. The view itself comes from the resolver, whose switch
 * step reads the same parameter.
 */
final readonly class SwitchListener
{
    /**
     * @param int         $status       the redirect status: 302, 303 or 307
     * @param string|null $cacheControl the Cache-Control of the redirect, or null to leave it alone
     */
    public function __construct(
        private SwitchParameter $parameter,
        private SwitchUrlGenerator $urls,
        private SwitchTarget $target = SwitchTarget::Current,
        private int $status = 302,
        private ?string $cacheControl = 'no-store, private',
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

        $response = new RedirectResponse($this->urls->redirectUrl($request, $this->target), $this->status);
        if (null !== $this->cacheControl) {
            $response->headers->set('Cache-Control', $this->cacheControl);
        }

        $event->setResponse($response);
    }
}
