<?php

declare(strict_types=1);

namespace DeviceViewBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks that the framework session is enabled when device_view.remember.storage is "session":
 * without it, reads find nothing and every write would fail at runtime.
 *
 * The extension sets the PARAMETER build parameter to true when the session storage is
 * configured. FrameworkBundle defines session.factory when framework.session is enabled.
 */
final class CheckSessionStoragePass implements CompilerPassInterface
{
    public const string PARAMETER = '.device_view.session_storage';

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER) || true !== $container->getParameter(self::PARAMETER)) {
            return;
        }

        if (!$container->has('session.factory')) {
            throw new InvalidConfigurationException('device_view.remember.storage "session" needs the framework session: enable framework.session, or set device_view.remember.storage to another storage.');
        }
    }
}
