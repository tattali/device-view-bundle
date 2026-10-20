<?php

declare(strict_types=1);

namespace DeviceViewBundle\Remember;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the view in the session (device_view.remember.storage: session), under
 * device_view.remember.session.key.
 *
 * It never starts a session just to read: only a request that carries the cookie of an earlier
 * session is read. A write starts the session, whose cookie the framework sets. The view lasts as
 * long as the session, so lifetimes do not apply.
 */
final readonly class SessionStorage implements ViewStorageInterface
{
    /**
     * @param string $key the session attribute holding the view name
     */
    public function __construct(
        private string $key = '_device_view',
    ) {
    }

    #[\Override]
    public function read(Request $request): mixed
    {
        if (!$request->hasPreviousSession()) {
            return null;
        }

        return $request->getSession()->get($this->key);
    }

    /**
     * Needs the framework session (framework.session), which the container build requires.
     */
    #[\Override]
    public function write(Request $request, Response $response, string $name, int $lifetime): void
    {
        $request->getSession()->set($this->key, $name);
    }

    #[\Override]
    public function clear(Request $request, Response $response): void
    {
        if ($request->hasPreviousSession()) {
            $request->getSession()->remove($this->key);
        }
    }

    /**
     * The session id comes in a cookie.
     */
    #[\Override]
    public function varyHeaders(): array
    {
        return ['Cookie'];
    }
}
