<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration())
    // the bundle's definition and service files are not autoloaded
    ->addPathToScan(__DIR__.'/config', isDev: false)
;
