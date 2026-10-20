<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Remember\NullStorage;
use DeviceViewBundle\Tests\App\Remember\ArrayStorage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * remember.storage none, and a custom storage service, through the kernel.
 */
final class StorageTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    #[Group('STO-01')]
    public function testStorageNoneNeverWrites(): void
    {
        $this->boot(['remember' => ['storage' => 'none']], framework: ['session' => ['storage_factory_id' => 'session.storage.factory.mock_file']]);

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));
        self::assertNoCookie($response);
        \assert(null !== $this->kernel);
        self::assertDirectoryDoesNotExist($this->kernel->getCacheDir().'/sessions', 'no session created');
    }

    public function testStorageNoneNeverRemembersARequestedView(): void
    {
        $this->boot(['remember' => ['storage' => 'none']]);

        $response = $this->handle(self::request('/set-view/tablet?remember=1'));

        self::assertSame('tablet forced', $response->getContent());
        self::assertNoCookie($response);
    }

    #[Group('STO-02')]
    public function testStorageNoneNeverReads(): void
    {
        $this->boot(['remember' => ['storage' => 'none']]);
        $request = self::request('/view', cookies: ['device_view' => 'mobile']);

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop detector', $response->getContent());
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        \assert($context instanceof DeviceViewContext);
        self::assertSame(['User-Agent'], $context->consultedInputs, 'no cookie is consulted');
        self::assertSame(['User-Agent'], $response->getVary());
    }

    #[Group('STO-03')]
    public function testStorageNoneNeverExpiresAForgedCookie(): void
    {
        $this->boot(['remember' => ['storage' => 'none']]);

        $response = $this->handle(self::request('/view', cookies: ['device_view' => 'phone']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop detector', $response->getContent());
        self::assertNoCookie($response);
    }

    public function testStorageNoneIsTheNullStorage(): void
    {
        $this->boot(['remember' => ['storage' => 'none']]);

        self::assertInstanceOf(NullStorage::class, $this->service('test.device_view.storage'));
        self::assertSame($this->service('test.device_view.storage'), $this->service('test.view_storage_interface'));
    }

    #[Group('STO-04')]
    public function testACustomStorageServiceRemembersTheView(): void
    {
        $this->boot(['remember' => ['storage' => 'app.array_storage']], ['app.array_storage' => ArrayStorage::class]);

        $switch = $this->handle(self::request('/view?device_view=tablet'));
        $next = $this->handle(Request::create('http://localhost/view'));

        self::assertSame(302, $switch->getStatusCode());
        self::assertNoCookie($switch);
        self::assertSame(200, $next->getStatusCode());
        self::assertSame('tablet remembered', $next->getContent());
    }

    #[Group('STO-05')]
    public function testTheInterfaceAliasFollowsTheConfiguration(): void
    {
        $this->boot(['remember' => ['storage' => 'app.array_storage']], ['app.array_storage' => ArrayStorage::class]);

        self::assertSame($this->service('app.array_storage'), $this->service('test.device_view.storage'));
        self::assertSame($this->service('app.array_storage'), $this->service('test.view_storage_interface'));
    }
}
