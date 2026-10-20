<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Remember\SessionStorage;
use DeviceViewBundle\Tests\App\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

/**
 * Remembering the view in the session (remember.storage: session), through the kernel, with the
 * framework session on the mock file storage.
 */
final class SessionTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    private const string SESSION_NAME = 'MOCKSESSID';

    #[Group('SES-01')]
    public function testASwitchIsWrittenToTheSession(): void
    {
        $this->bootWithSession();

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));
        self::assertSame([self::SESSION_NAME], self::cookieNames($response), 'no device_view cookie');
        self::assertSame(['_device_view' => 'mobile'], $this->sessionOf($response));
    }

    #[Group('SES-02')]
    public function testThePreviousSessionIsRead(): void
    {
        $this->bootWithSession();
        $request = self::request('/view', 'iphone', cookies: $this->previousSession(['_device_view' => 'tablet']));

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet remembered', $response->getContent());
        self::assertSame(['Cookie'], $this->consultedInputs($request));
        self::assertSame(['Cookie'], $response->getVary());
    }

    #[Group('SES-03')]
    public function testASessionIsNeverStartedToRead(): void
    {
        $this->bootWithSession();
        $request = self::request('/view', 'iphone');

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $this->consultedInputs($request));
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
        $this->assertNoSessionStarted($response);
    }

    public function testASessionIsNeverStartedToReadWhateverTheRequestCookies(): void
    {
        $this->bootWithSession();

        $response = $this->handle(self::request('/view', 'iphone', cookies: ['device_view' => 'tablet']));

        self::assertSame('mobile detector', $response->getContent(), 'the cookie storage is not read');
        $this->assertNoSessionStarted($response);
    }

    public function testLifetimesAreIgnored(): void
    {
        $this->bootWithSession(['lifetime' => 3600]);

        $response = $this->handle(self::request('/view?device_view=mobile'));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame(self::SESSION_NAME, $cookies[0]->getName());
        self::assertSame(0, $cookies[0]->getExpiresTime(), 'the framework\'s session cookie');
        self::assertSame(['_device_view' => 'mobile'], $this->sessionOf($response));
    }

    #[Group('SES-04')]
    public function testTheConsentedLifetimeIsIgnored(): void
    {
        $this->bootWithSession(consent: ['provider' => 'tarteaucitron']);

        $response = $this->handle(self::request('/view?device_view=mobile', cookies: ['tarteaucitron' => '!device_view=true']));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies, 'no device_view cookie');
        self::assertSame(self::SESSION_NAME, $cookies[0]->getName());
        self::assertSame(0, $cookies[0]->getExpiresTime(), 'the framework\'s session cookie, unchanged');
        self::assertSame(['_device_view' => 'mobile'], $this->sessionOf($response));
    }

    #[Group('SES-07')]
    public function testADetectedViewWithConsentDeniedIsNotWritten(): void
    {
        $this->bootWithSession(['detected_view' => true], consent: ['provider' => 'tarteaucitron']);

        $response = $this->handle(self::request('/view', 'iphone', cookies: ['tarteaucitron' => '!device_view=false']));

        self::assertSame(200, $response->getStatusCode());
        $this->assertNoSessionStarted($response);
    }

    #[Group('SES-08')]
    public function testTheRouteDefaultStopsASessionWrite(): void
    {
        $this->bootWithSession();

        $response = $this->handle(self::request('/no-remember?device_view=mobile'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/no-remember', $response->headers->get('Location'));
        $this->assertNoSessionStarted($response);
    }

    #[Group('SES-05')]
    public function testTheSessionKeyIsConfigurable(): void
    {
        $this->bootWithSession(['session' => ['key' => 'dv']]);

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSame(['dv' => 'mobile'], $this->sessionOf($response));
    }

    public function testTheConfiguredSessionKeyIsRead(): void
    {
        $this->bootWithSession(['session' => ['key' => 'dv']]);
        $cookies = $this->previousSession(['_device_view' => 'mobile', 'dv' => 'tablet']);

        self::assertSame('tablet remembered', $this->handle(self::request('/view', cookies: $cookies))->getContent());
    }

    #[Group('SES-06')]
    public function testADetectedViewIsWrittenToTheSession(): void
    {
        $this->bootWithSession(['detected_view' => true]);

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['_device_view' => 'mobile'], $this->sessionOf($response));
    }

    public function testADetectedViewIsNotWrittenByDefault(): void
    {
        $this->bootWithSession();

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame('mobile detector', $response->getContent());
        $this->assertNoSessionStarted($response);
    }

    public function testASwitchKeepsTheOtherValuesOfThePreviousSession(): void
    {
        $this->bootWithSession();
        $cookies = $this->previousSession(['_device_view' => 'tablet', 'cart' => 3]);

        $response = $this->handle(self::request('/view?device_view=mobile', cookies: $cookies));

        self::assertSame('/view', $response->headers->get('Location'));
        self::assertSame(['_device_view' => 'mobile', 'cart' => 3], $this->sessionOf($response, $cookies[self::SESSION_NAME]));
    }

    #[Group('VAL-12')]
    public function testAForgedSessionValueIsRemoved(): void
    {
        $this->bootWithSession(services: ['logger' => TestLogger::class]);
        $cookies = $this->previousSession(['_device_view' => 'phone', 'cart' => 3]);

        $response = $this->handle(self::request('/view', cookies: $cookies));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop detector', $response->getContent());
        self::assertSame(['cart' => 3], $this->sessionOf($response, $cookies[self::SESSION_NAME]));
        self::assertSame([], self::cookieNames($response), 'the session keeps its cookie');
        $logger = $this->service('logger');
        \assert($logger instanceof TestLogger);
        self::assertSame(['Ignored an invalid device view from the remember storage.'], $logger->messages(LogLevel::DEBUG));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonStringSessionValues(): iterable
    {
        yield 'integer' => [3];
        yield 'array' => [['a' => 'mobile']];
    }

    #[Group('VAL-14')]
    #[DataProvider('nonStringSessionValues')]
    public function testANonStringSessionValueIsRemoved(mixed $value): void
    {
        $this->bootWithSession(services: ['logger' => TestLogger::class]);
        $cookies = $this->previousSession(['_device_view' => $value, 'cart' => 3]);

        $response = $this->handle(self::request('/view', cookies: $cookies));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop detector', $response->getContent());
        self::assertSame(['cart' => 3], $this->sessionOf($response, $cookies[self::SESSION_NAME]));
        $logger = $this->service('logger');
        \assert($logger instanceof TestLogger);
        self::assertSame(['Ignored an invalid device view from the remember storage.'], $logger->messages(LogLevel::DEBUG));
    }

    public function testTheStorageIsTheSessionStorage(): void
    {
        $this->bootWithSession();

        self::assertInstanceOf(SessionStorage::class, $this->service('test.device_view.storage'));
        self::assertSame($this->service('test.device_view.storage'), $this->service('test.view_storage_interface'));
    }

    /**
     * @param array<string, mixed>        $remember the remember configuration added to storage: session
     * @param array<string, class-string> $services
     * @param array<string, mixed>        $consent  the consent configuration
     */
    private function bootWithSession(array $remember = [], array $services = [], array $consent = []): void
    {
        $this->boot(
            ['remember' => ['storage' => 'session'] + $remember, 'consent' => $consent],
            $services,
            ['session' => ['storage_factory_id' => 'session.storage.factory.mock_file']],
        );
        // the kernels of one configuration share their cache directory, and so their sessions
        new Filesystem()->remove($this->sessionDir());
    }

    /**
     * Saves a session holding $values, as an earlier request would have.
     *
     * @param array<string, mixed> $values
     *
     * @return array{MOCKSESSID: string} the request cookie of the session
     */
    private function previousSession(array $values): array
    {
        $session = new Session($this->sessionStorage());
        foreach ($values as $key => $value) {
            $session->set($key, $value);
        }
        $session->save();

        return [self::SESSION_NAME => $session->getId()];
    }

    /**
     * The values of the saved session whose id the response cookie gives, or $id.
     *
     * @return array<mixed>
     */
    private function sessionOf(Response $response, ?string $id = null): array
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (self::SESSION_NAME === $cookie->getName()) {
                $id = $cookie->getValue();
            }
        }
        self::assertNotNull($id, 'the response has a session');

        $storage = $this->sessionStorage();
        $storage->setId($id);

        return new Session($storage)->all();
    }

    /**
     * Asserts that the request started no session: the response sets no cookie and no session
     * was saved.
     */
    private function assertNoSessionStarted(Response $response): void
    {
        self::assertSame([], self::cookieNames($response), 'no session cookie');
        self::assertSame([], self::filesIn($this->sessionDir()), 'no session saved');
    }

    /**
     * @return list<string>
     */
    private static function cookieNames(Response $response): array
    {
        return array_values(array_map(static fn (Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies()));
    }

    /**
     * @return list<string>
     */
    private function consultedInputs(Request $request): array
    {
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        \assert($context instanceof DeviceViewContext);

        return $context->consultedInputs;
    }

    /**
     * @return list<string>
     */
    private static function filesIn(string $directory): array
    {
        $files = glob($directory.'/*');

        return false === $files ? [] : $files;
    }

    private function sessionStorage(): MockFileSessionStorage
    {
        return new MockFileSessionStorage($this->sessionDir(), self::SESSION_NAME);
    }

    private function sessionDir(): string
    {
        \assert(null !== $this->kernel);

        return $this->kernel->getCacheDir().'/sessions';
    }
}
