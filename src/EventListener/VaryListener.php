<?php

declare(strict_types=1);

namespace DeviceViewBundle\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Lists in the Vary header of the main response the request inputs the view resolution consulted,
 * on kernel.response at priority 0, before the remember listener.
 *
 * The values the response already has stay first, as they are. The consulted inputs follow in
 * the order they were consulted, as one header line, without the names already present, compared
 * without case. A request that never used its view consulted nothing and gets no Vary; neither
 * does a view that a source reading no header decided (a switch, a forced view). Skipped requests
 * still resolve their view, so they vary too. Sub-request responses are left alone: only the main
 * response reaches the client and the caches.
 *
 * The listener is registered only when http_cache.vary is true.
 */
final readonly class VaryListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $context = $event->getRequest()->attributes->get(DeviceViewContext::ATTRIBUTE);
        if (!$context instanceof DeviceViewContext) {
            return;
        }

        $response = $event->getResponse();
        $present = self::listedNames($response);
        $missing = [];
        foreach ($context->consultedInputs as $input) {
            $name = strtolower($input);
            if (!\in_array($name, $present, true)) {
                $present[] = $name;
                $missing[] = $input;
            }
        }

        if ([] !== $missing) {
            $response->setVary(implode(', ', $missing), false);
        }
    }

    /**
     * The names the Vary lines of the response already list, lower-cased.
     *
     * @return list<string>
     */
    private static function listedNames(Response $response): array
    {
        $names = [];
        foreach ($response->headers->all('vary') as $line) {
            foreach (explode(',', strtolower($line ?? '')) as $name) {
                $names[] = trim($name);
            }
        }

        return $names;
    }
}
