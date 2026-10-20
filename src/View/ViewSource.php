<?php

declare(strict_types=1);

namespace DeviceViewBundle\View;

/**
 * What decided the view of a request, in the resolution order (design.md section 5).
 */
enum ViewSource: string
{
    /** DeviceView::setView() */
    case Forced = 'forced';
    /** the switch query parameter */
    case Switch = 'switch';
    /** the remember storage (cookie, session or a custom storage) */
    case Remembered = 'remembered';
    /** the trusted header set by a proxy or CDN */
    case TrustedHeader = 'trusted_header';
    /** the device detector */
    case Detector = 'detector';
    /** device_view.default_view: nothing else decided */
    case Default = 'default';
}
