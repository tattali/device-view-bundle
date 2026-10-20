<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\DeviceView;
use DeviceViewBundle\DeviceViewBundle;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The application of the functional tests: FrameworkBundle, this bundle and the routes of
 * config/routes.php.
 *
 * Each bundle configuration gets its own cache directory and container class, so one test
 * run can boot the kernel with several configurations.
 *
 * Tests read the bundle's private services through the public aliases of TEST_ALIASES.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Public aliases of the bundle's services, keyed by the public id tests ask for.
     *
     * @var array<string, string>
     */
    public const array TEST_ALIASES = [
        'test.device_view.detector' => 'device_view.detector',
        'test.device_detector_interface' => DeviceDetectorInterface::class,
        'test.device_view.resolver' => 'device_view.resolver',
        'test.view_resolver_interface' => ViewResolverInterface::class,
        'test.device_view.device_view' => 'device_view.device_view',
        'test.device_view' => DeviceView::class,
    ];

    private static ?string $runDir = null;

    private readonly string $configHash;

    /** @var callable|null */
    private $exceptionHandlerBeforeBoot;

    /**
     * SHELL_VERBOSITY before boot, in $_ENV, $_SERVER and getenv(); null when it was not set.
     *
     * @var array{env: mixed, server: mixed, getenv: string|false}|null
     */
    private ?array $shellVerbosityBeforeBoot = null;

    /**
     * @param array<string, mixed>        $deviceViewConfig the device_view configuration
     * @param array<string, class-string> $services         public services of the application, by id
     */
    public function __construct(
        private readonly array $deviceViewConfig = [],
        string $environment = 'test',
        bool $debug = false,
        private readonly array $services = [],
    ) {
        parent::__construct($environment, $debug);
        $this->configHash = hash('xxh128', serialize([$deviceViewConfig, $environment, $debug, $services]));
    }

    #[\Override]
    public function boot(): void
    {
        if (!$this->booted) {
            $this->exceptionHandlerBeforeBoot = self::currentExceptionHandler();
            $this->shellVerbosityBeforeBoot = [
                'env' => $_ENV['SHELL_VERBOSITY'] ?? null,
                'server' => $_SERVER['SHELL_VERBOSITY'] ?? null,
                'getenv' => getenv('SHELL_VERBOSITY'),
            ];
        }

        parent::boot();
    }

    /**
     * Also removes the exception handlers that booting added, and restores SHELL_VERBOSITY.
     *
     * On Symfony 8.0, FrameworkBundle::boot() registers the ErrorHandler and nothing removes
     * it, so PHPUnit marks the test risky. A debug kernel sets SHELL_VERBOSITY to 3 for the
     * whole process, so every kernel booted after it would print its info logs.
     */
    #[\Override]
    public function shutdown(): void
    {
        parent::shutdown();

        while (null !== ($handler = self::currentExceptionHandler()) && $handler !== $this->exceptionHandlerBeforeBoot) {
            restore_exception_handler();
        }

        if (null !== $before = $this->shellVerbosityBeforeBoot) {
            self::restore($_ENV, $before['env']);
            self::restore($_SERVER, $before['server']);
            putenv(false === $before['getenv'] ? 'SHELL_VERBOSITY' : 'SHELL_VERBOSITY='.$before['getenv']);
            $this->shellVerbosityBeforeBoot = null;
        }
    }

    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DeviceViewBundle();
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return self::runDir().'/'.$this->configHash.'/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return self::runDir().'/'.$this->configHash.'/log';
    }

    /**
     * FrameworkBundle writes reference.php into the config directory when it builds the
     * container. The test app points it to a directory that does not exist, so nothing is
     * written into the repository.
     *
     * @return array<array-key, mixed>
     */
    #[\Override]
    protected function getKernelParameters(): array
    {
        return ['.kernel.config_dir' => self::runDir().'/'.$this->configHash.'/config'] + parent::getKernelParameters();
    }

    #[\Override]
    protected function getContainerClass(): string
    {
        return parent::getContainerClass().'_'.$this->configHash;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'device-view-bundle-tests',
            'test' => true,
        ]);
        $container->extension('device_view', $this->deviceViewConfig);

        $services = $container->services();
        $services
            ->set(TestController::class)
            ->args([new Reference(DeviceView::class), new Reference('http_kernel')])
            ->tag('controller.service_arguments')
        ;
        $services
            ->set(ControllerCall::class)
            ->public()
            ->args([new Reference(DeviceView::class)])
            ->tag('kernel.event_listener', ['event' => KernelEvents::CONTROLLER, 'method' => 'onKernelController'])
        ;
        $services
            ->set(RequestRecorder::class)
            ->public()
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => -1000])
        ;
        foreach ($this->services as $id => $class) {
            $services->set($id, $class)->public();
        }
        foreach (self::TEST_ALIASES as $alias => $id) {
            $services->alias($alias, $id)->public();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/config/routes.php');
    }

    /**
     * @param array<array-key, mixed> $variables
     */
    private static function restore(array &$variables, mixed $value): void
    {
        if (null === $value) {
            unset($variables['SHELL_VERBOSITY']);
        } else {
            $variables['SHELL_VERBOSITY'] = $value;
        }
    }

    private static function currentExceptionHandler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    /**
     * The cache root of this PHP process, removed when the process ends.
     *
     * Containers are never shared between processes: Infection runs each mutant in its own
     * process, and a container compiled from the unmutated code would hide the mutant.
     */
    private static function runDir(): string
    {
        if (null === self::$runDir) {
            $runDir = sys_get_temp_dir().'/device-view-bundle-tests/'.bin2hex(random_bytes(8));
            register_shutdown_function(static function () use ($runDir): void {
                new Filesystem()->remove($runDir);
            });
            self::$runDir = $runDir;
        }

        return self::$runDir;
    }
}
