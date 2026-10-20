<?php

declare(strict_types=1);

namespace DeviceViewBundle\Detector;

use Detection\MobileDetect;
use DeviceViewBundle\View\DeviceType;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Detects the device type with Mobile Detect (mobiledetect/mobiledetectlib 4).
 *
 * Each call builds a new MobileDetect from the configured headers of the Request only, with the
 * auto-initialisation from $_SERVER turned off, so nothing is kept from one request to the next.
 */
final readonly class MobileDetectDetector implements DeviceDetectorInterface
{
    /**
     * @param non-empty-list<string> $headers the request headers passed to Mobile Detect
     * @param CacheInterface|null    $cache   a PSR-16 cache for Mobile Detect's results
     */
    public function __construct(
        private array $headers = ['User-Agent'],
        private ?CacheInterface $cache = null,
    ) {
    }

    #[\Override]
    public function detect(Request $request): ?DeviceType
    {
        $httpHeaders = [];
        foreach ($this->headers as $name) {
            $value = $request->headers->get($name);
            if (null !== $value) {
                $httpHeaders['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
            }
        }

        $detect = new MobileDetect($this->cache, ['autoInitOfHttpHeaders' => false]);
        $detect->setHttpHeaders($httpHeaders);
        if (!$detect->hasUserAgent() || $detect->isUserAgentEmpty()) {
            return null;
        }

        return match (true) {
            $detect->isTablet() => DeviceType::Tablet,
            $detect->isMobile() => DeviceType::Mobile,
            default => DeviceType::Desktop,
        };
    }

    #[\Override]
    public function varyHeaders(): array
    {
        return $this->headers;
    }
}
