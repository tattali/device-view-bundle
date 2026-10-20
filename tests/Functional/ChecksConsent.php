<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Consent\ConsentCheckerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runs a row of the consent area of docs/scenarios.md (section 10), for the test classes that
 * also use HandlesRequests and AssertsCookies.
 */
trait ChecksConsent
{
    /**
     * Boots the kernel with $config, asks the configured consent checker about the request, then
     * switches with the same request cookies and headers: the switch is remembered for the
     * default consented_lifetime when the answer is true, and for the browser session otherwise.
     *
     * @param array<string, mixed>        $config   the device_view configuration
     * @param array<string, mixed>        $cookies  the request cookies, as PHP decodes them
     * @param bool|null                   $expected what hasConsent() returns
     * @param array<string, string>       $headers  more request headers
     * @param array<string, class-string> $services public services of the application, by id
     */
    private function assertConsentRow(array $config, array $cookies, ?bool $expected, array $headers = [], array $services = []): void
    {
        $this->boot($config, $services);
        $checker = $this->service('test.device_view.consent_checker');
        Assert::assertInstanceOf(ConsentCheckerInterface::class, $checker);

        Assert::assertSame($expected, $checker->hasConsent(self::consentRequest('/view', $cookies, $headers)), 'hasConsent');

        $response = $this->handle(self::consentRequest('/view?device_view=mobile', $cookies, $headers));
        Assert::assertSame(302, $response->getStatusCode());
        Assert::assertSame('/view', $response->headers->get('Location'));
        self::assertSetCookie(true === $expected ? self::sc('mobile', 31536000) : self::sc('mobile'), $response);
    }

    /**
     * @param array<string, mixed>  $cookies
     * @param array<string, string> $headers
     */
    private static function consentRequest(string $path, array $cookies, array $headers): Request
    {
        $request = self::request($path, cookies: $cookies);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }
}
