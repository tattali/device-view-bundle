<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use DeviceViewBundle\DeviceViewBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The application of the functional tests: FrameworkBundle, this bundle and the routes of
 * config/routes.php.
 *
 * Each bundle configuration gets its own cache directory and container class, so one test
 * run can boot the kernel with several configurations.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private static ?string $runDir = null;

    private readonly string $configHash;

    /** @var callable|null */
    private $exceptionHandlerBeforeBoot;

    /**
     * @param array<string, mixed> $deviceViewConfig the device_view configuration
     */
    public function __construct(
        private readonly array $deviceViewConfig = [],
        string $environment = 'test',
        bool $debug = false,
    ) {
        parent::__construct($environment, $debug);
        $this->configHash = hash('xxh128', serialize([$deviceViewConfig, $environment, $debug]));
    }

    #[\Override]
    public function boot(): void
    {
        if (!$this->booted) {
            $this->exceptionHandlerBeforeBoot = self::currentExceptionHandler();
        }

        parent::boot();
    }

    /**
     * Also removes the exception handlers that booting added.
     *
     * On Symfony 8.0, FrameworkBundle::boot() registers the ErrorHandler and nothing removes
     * it, so PHPUnit marks the test risky.
     */
    #[\Override]
    public function shutdown(): void
    {
        parent::shutdown();

        while (null !== ($handler = self::currentExceptionHandler()) && $handler !== $this->exceptionHandlerBeforeBoot) {
            restore_exception_handler();
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

        $container->services()
            ->set(TestController::class)
            ->tag('controller.service_arguments')
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/config/routes.php');
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
