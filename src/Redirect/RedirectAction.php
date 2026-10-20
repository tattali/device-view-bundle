<?php

declare(strict_types=1);

namespace DeviceViewBundle\Redirect;

/**
 * What a device redirect does when the view is served on another host
 * (device_view.redirect.<view>.action).
 */
enum RedirectAction: string
{
    /** to the same page on the view's host: its path info and, with keep_query, its query */
    case Redirect = 'redirect';
    /** to the home page of the view's host */
    case RedirectWithoutPath = 'redirect_without_path';
    /** no redirect */
    case None = 'none';
}
