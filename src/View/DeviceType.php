<?php

declare(strict_types=1);

namespace DeviceViewBundle\View;

/**
 * The views a site can serve. The values are the canonical view names.
 */
enum DeviceType: string
{
    case Mobile = 'mobile';
    case Tablet = 'tablet';
    case Desktop = 'desktop';
}
