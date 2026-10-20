<?php

declare(strict_types=1);

namespace DeviceViewBundle;

use DeviceViewBundle\DependencyInjection\Compiler\CheckConfiguredServicesPass;
use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\Detector\MobileDetectDetector;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Serves the mobile, tablet or desktop view of a site.
 *
 * The configuration tree and the services live in config/, one file per concern.
 */
final class DeviceViewBundle extends AbstractBundle
{
    protected string $extensionAlias = 'device_view';

    #[\Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CheckConfiguredServicesPass());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[\Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        /** @var array{id: non-empty-string, mobile_detect: array{headers: non-empty-list<string>, cache: string|null}} $detector */
        $detector = $config['detector'];
        $checks = [];
        $id = 'auto' === $detector['id'] ? self::autoDetector() : $detector['id'];

        if ('mobile_detect' === $id) {
            if (!self::isInstalled('mobiledetect/mobiledetectlib', 'Detection\MobileDetect')) {
                throw new \LogicException('The "mobile_detect" detector needs mobiledetect/mobiledetectlib. Run "composer require mobiledetect/mobiledetectlib", or set device_view.detector.id to another detector.');
            }

            $cache = $detector['mobile_detect']['cache'];
            $container->services()
                ->set('device_view.detector.mobile_detect', MobileDetectDetector::class)
                ->args([$detector['mobile_detect']['headers'], null === $cache ? null : new Reference($cache)])
            ;
            if (null !== $cache) {
                $checks[] = [$cache, 'device_view.detector.mobile_detect.cache', 'Psr\SimpleCache\CacheInterface'];
            }
            $id = 'device_view.detector.mobile_detect';
        } else {
            $checks[] = [$id, 'device_view.detector.id', DeviceDetectorInterface::class];
        }

        $container->services()->alias('device_view.detector', $id);
        $builder->setParameter(CheckConfiguredServicesPass::PARAMETER, $checks);
    }

    /**
     * The detector that detector.id: auto stands for.
     */
    private static function autoDetector(): string
    {
        if (self::isInstalled('mobiledetect/mobiledetectlib', 'Detection\MobileDetect')) {
            return 'mobile_detect';
        }

        throw new \LogicException('No device detector is installed. Run `composer require mobiledetect/mobiledetectlib` (or `matomo/device-detector`), or set `device_view.detector.id` to `client_hints` or to your own service id.');
    }

    private static function isInstalled(string $package, string $class): bool
    {
        return ContainerBuilder::willBeAvailable($package, $class, ['tattali/device-view-bundle']);
    }
}
