<?php

declare(strict_types=1);

namespace DeviceViewBundle\Redirect;

use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decides where a view served on its own host (device_view.redirect.<view>) sends a request:
 * the device redirect, and the switch to that view.
 *
 * A view redirects when its redirect is enabled, its action is not none and the request was not
 * sent to its host (RedirectHost::isCurrentHost()), so a request on the right host is never
 * redirected again. The redirect route default of the matched route, when it sets one, replaces
 * the action of the view on that route; it never enables a redirect. The Location is built from the configured host only, never from the Host
 * header, and carries the view in the switch parameter.
 *
 * A host set with an env var is checked here, at runtime: an invalid one disables the redirect of
 * its view, with an error log. A redirect to another host logs a warning when the view is
 * remembered in a cookie without a domain, which that host cannot read.
 *
 * @internal
 */
final readonly class RedirectPolicy
{
    /**
     * @param array<value-of<DeviceType>, array{host: string, action: RedirectAction, status: int, keep_query: bool}> $redirects           the enabled redirects, by view
     * @param string|null                                                                                             $cookieWithoutDomain the name of the remember cookie when the storage is a cookie without a domain, else null
     */
    public function __construct(
        private array $redirects,
        private ViewNames $names,
        private SwitchUrlGenerator $urls,
        private ?string $cookieWithoutDomain = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Whether any view has its redirect enabled.
     */
    public function hasRedirects(): bool
    {
        return [] !== $this->redirects;
    }

    /**
     * Whether the view is served on the current host: its redirect is enabled, its action is not
     * none and its host is the one the request was sent to. It logs nothing.
     *
     * @param RedirectAction|null $routeAction the action the route default sets, which replaces the configured one
     */
    public function servesHere(Request $request, DeviceType $view, ?RedirectAction $routeAction = null): bool
    {
        $redirect = $this->redirects[$view->value] ?? null;
        if (null === $redirect || RedirectAction::None === ($routeAction ?? $redirect['action'])) {
            return false;
        }

        return RedirectHost::parse($redirect['host'])?->isCurrentHost($request) ?? false;
    }

    /**
     * The Location and status of the device redirect of a request served with the view, or null
     * when the request stays here.
     *
     * @param RedirectAction|null $routeAction the action the route default sets, which replaces the configured one
     */
    public function deviceRedirect(Request $request, DeviceType $view, ?RedirectAction $routeAction = null): ?RedirectTarget
    {
        $target = $this->hostFor($request, $view, $routeAction);
        if (null === $target) {
            return null;
        }

        [$host, $redirect, $action] = $target;
        $url = $this->urls->hostUrl($request, $host, $this->names->nameOf($view), RedirectAction::Redirect === $action, $redirect['keep_query']);

        return new RedirectTarget($url, $redirect['status']);
    }

    /**
     * Where a switch to the view leads when the view is served on another host: where its device
     * redirect would go, or the home page of that host for the switch target home; null when
     * the switch stays on this host.
     *
     * @param RedirectAction|null $routeAction the action the route default sets, which replaces the configured one
     */
    public function switchUrl(Request $request, DeviceType $view, SwitchTarget $switchTarget, ?RedirectAction $routeAction = null): ?string
    {
        $target = $this->hostFor($request, $view, $routeAction);
        if (null === $target) {
            return null;
        }

        [$host, $redirect, $action] = $target;
        $withPath = SwitchTarget::Home !== $switchTarget && RedirectAction::Redirect === $action;

        return $this->urls->hostUrl($request, $host, $this->names->nameOf($view), $withPath, $redirect['keep_query']);
    }

    /**
     * The host the request goes to for the view, with the redirect options of the view and the
     * action that applies (the route's, else the configured one), or null when it stays here.
     *
     * @return array{RedirectHost, array{host: string, action: RedirectAction, status: int, keep_query: bool}, RedirectAction}|null
     */
    private function hostFor(Request $request, DeviceType $view, ?RedirectAction $routeAction): ?array
    {
        $redirect = $this->redirects[$view->value] ?? null;
        if (null === $redirect) {
            return null;
        }

        $action = $routeAction ?? $redirect['action'];
        if (RedirectAction::None === $action) {
            return null;
        }

        $host = RedirectHost::parse($redirect['host']);
        if (null === $host) {
            $this->logger?->error(\sprintf('The redirect host "%1$s" of the %2$s view is invalid: expected an absolute http(s) URL without query, fragment or user info. The %2$s redirect is disabled.', $redirect['host'], $view->value));

            return null;
        }

        if ($host->isCurrentHost($request)) {
            return null;
        }

        if (null !== $this->cookieWithoutDomain) {
            $this->logger?->warning(\sprintf('The %s redirect goes to %s, but the %s cookie has no domain, so that host cannot read the remembered view. Set device_view.remember.cookie.domain to a domain both hosts share.', $view->value, $host->authority(), $this->cookieWithoutDomain));
        }

        return [$host, $redirect, $action];
    }
}
