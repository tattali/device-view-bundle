<?php

declare(strict_types=1);

namespace DeviceViewBundle\ViewSwitch;

/**
 * Where a switch leads (device_view.switch.target).
 */
enum SwitchTarget: string
{
    /** the same page, without the switch parameter */
    case Current = 'current';
    /** the home page: the base URL and "/" */
    case Home = 'home';
    /** no redirect: the switch applies to the request that carries it */
    case None = 'none';
}
