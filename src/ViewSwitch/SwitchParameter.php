<?php

declare(strict_types=1);

namespace DeviceViewBundle\ViewSwitch;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the switch query parameter (device_view.switch.parameter) of a main request.
 *
 * The switch applies only when it is enabled, the request method is one of redirect.methods and
 * the request is not skipped. Its value is a view token: anything that is not a configured view
 * name is ignored and logged at debug level. DeviceViewContext::readSwitch() calls it once per
 * request, for the switch listener and the resolver alike.
 *
 * @internal
 */
final readonly class SwitchParameter
{
    /**
     * @param string       $name    the query parameter
     * @param bool         $enabled device_view.switch.enabled
     * @param list<string> $methods the request methods the switch applies to, upper case
     */
    public function __construct(
        private ViewNames $names,
        private string $name = 'device_view',
        private bool $enabled = true,
        private array $methods = ['GET', 'HEAD'],
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The view the switch parameter asks for, or null when there is none, it does not apply or
     * its value is invalid.
     */
    public function read(Request $request, DeviceViewContext $context): ?DeviceType
    {
        if (!$this->enabled || $context->skipped || !\in_array($request->getMethod(), $this->methods, true)) {
            return null;
        }

        // all(), not get(): get() throws on an array value such as ?device_view[]=x
        $query = $request->query->all();
        if (!\array_key_exists($this->name, $query)) {
            return null;
        }

        $view = $this->names->parse($query[$this->name]);
        if (null === $view) {
            $this->logger?->debug('Ignored an invalid device view from the switch parameter.');
        }

        return $view;
    }
}
