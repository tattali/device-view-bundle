<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // the bundle's definition and service files are not autoloaded
    ->addPathToScan(__DIR__.'/config', isDev: false)
    // optional: only the mobile_detect detector uses it, and the container build asks for it
    // when that detector is configured (suggest in composer.json, dev dependency for the tests)
    ->ignoreErrorsOnPackage('mobiledetect/mobiledetectlib', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // comes with mobiledetect/mobiledetectlib, whose constructor takes its CacheInterface
    ->ignoreErrorsOnPackage('psr/simple-cache', [ErrorType::SHADOW_DEPENDENCY])
;
