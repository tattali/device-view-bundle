<?php

declare(strict_types=1);

namespace DeviceViewBundle\Remember;

/**
 * Why the view would be remembered (design.md section 7).
 */
enum RememberTrigger: string
{
    /** a switch, or DeviceView::setView() asked to remember */
    case Switch = 'switch';
    /** the view came from the detector or the trusted header */
    case Detected = 'detected';
}
