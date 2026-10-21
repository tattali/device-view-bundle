<?php

declare(strict_types=1);

namespace DeviceViewBundle\Redirect;

/**
 * Why the bundle redirects a request: a switch, or the host of the view (BeforeRedirectEvent).
 */
enum RedirectReason: string
{
    /** The switch listener redirects after a switch. */
    case Switch = 'switch';

    /** The device redirect listener sends the view to the host it is served on. */
    case Device = 'device';
}
