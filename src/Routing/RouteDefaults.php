<?php

declare(strict_types=1);

namespace DeviceViewBundle\Routing;

use DeviceViewBundle\Redirect\RedirectAction;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the per-route overrides from the defaults of the matched route: the redirect default
 * (route_defaults.redirect, _device_view_redirect by default) and the remember default
 * (route_defaults.remember, _device_view_remember by default).
 *
 * The RouterListener copies the defaults of the matched route into the request attributes, so
 * they are read there, after routing, and the route collection is never loaded. A query
 * parameter never sets them: only the route does.
 *
 * - The redirect default is redirect, redirect_without_path or none: it replaces the action of the
 *   redirect of the view on that route.
 * - The remember default is a boolean, or the string true, false, 1 or 0, as XML routes give it:
 *   false stops any write of the remember storage on that route; true lets the remember policy
 *   decide, so it never bypasses consent.
 *
 * Any other value is ignored, with a debug log.
 *
 * @internal
 */
final readonly class RouteDefaults
{
    private const array BOOLEAN_STRINGS = ['true' => true, '1' => true, 'false' => false, '0' => false];

    /**
     * @param string $redirectKey route_defaults.redirect
     * @param string $rememberKey route_defaults.remember
     */
    public function __construct(
        private string $redirectKey = '_device_view_redirect',
        private string $rememberKey = '_device_view_remember',
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The action the route sets for the redirect of the view, or null when it sets none or an
     * invalid one.
     */
    public function redirect(Request $request): ?RedirectAction
    {
        $value = $request->attributes->get($this->redirectKey);
        if (null === $value) {
            return null;
        }

        $action = \is_string($value) ? RedirectAction::tryFrom($value) : null;
        if (null === $action) {
            $this->ignore($this->redirectKey, $value);
        }

        return $action;
    }

    /**
     * Whether the route lets the view be remembered, or null when it says nothing or something
     * invalid.
     */
    public function remember(Request $request): ?bool
    {
        $value = $request->attributes->get($this->rememberKey);
        if (null === $value || \is_bool($value)) {
            return $value;
        }

        $remember = \is_string($value) ? self::BOOLEAN_STRINGS[$value] ?? null : null;
        if (null === $remember) {
            $this->ignore($this->rememberKey, $value);
        }

        return $remember;
    }

    private function ignore(string $key, mixed $value): void
    {
        $this->logger?->debug(\sprintf('Ignored the invalid route default %s %s.', $key, \is_string($value) ? '"'.$value.'"' : 'of type '.get_debug_type($value)));
    }
}
