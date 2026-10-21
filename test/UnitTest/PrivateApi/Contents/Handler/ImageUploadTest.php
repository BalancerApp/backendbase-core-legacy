<?php

declare(strict_types=1);

namespace UnitTest\PrivateApi\Contents\Handler;

use BackendBase\Domain\Collections\Interfaces\CollectionRepository;
use BackendBase\Domain\IdentityAndAccess\Exception\InsufficientPrivileges;
use BackendBase\Domain\IdentityAndAccess\Model\Permissions\Contents;
use BackendBase\Infrastructure\Persistence\Doctrine\Repository\FileRepository;
use BackendBase\Infrastructure\Persistence\Doctrine\Repository\GenericRepository;
use BackendBase\PrivateApi\Contents\Handler\GenericUploadImage;
use BackendBase\PrivateApi\Contents\Handler\UploadImages;
use BackendBase\Shared\Services\MessageBus\Interfaces\CommandBus;
use BackendBase\Shared\Services\RoleBasedAccessControl;
use Fixtures\Authentication;
use Fixtures\ImageUploadTestCase;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

use function basename;
use function getimagesize;
use function imagecolorat;
use function imagecreatefrompng;
use function json_decode;
use function str_replace;

use const IMAGETYPE_JPEG;
use const IMAGETYPE_PNG;

final class ImageUploadTest extends ImageUploadTestCase
{
    #[DataProvider('uploads')]
    public function testStoresResizedImagesAndReturnsTheirPublicPaths(
        string $target,
        string $format,
        array $query,
        array $dimensions,
        bool $base64,
        array $sourceDimensions = [1200, 600]
    ) : void {
        $stored = [];
        $files = $this->createMock(FileRepository::class);
        $files->expects($this->once())->method('addNewFile')
            ->willReturnCallback(static function (array $file) use (&$stored) : void {
                $stored = $file;
            });
        $handler = $this->handler($target, $files);
        $role = RoleBasedAccessControl::fromPermissions('editor', [Contents::CMS_EDIT]);

        $response = $this->upload($handler, [
            'loggedUserId' => Authentication::USER_ID, 'role' => $role,
            'contentId' => 'content-123', 'moduleName' => 'catalog',
        ], $query, $format, $base64, $sourceDimensions);

        $folder = $target === 'content' ? 'content/content-123' : 'catalog';
        $context = $target === 'content' ? ['contentId' => 'content-123'] : ['module' => 'catalog'];
        $path = 'app/images/' . $folder . '/' . $stored['id'] . '.' . $format;
        $this->assertTrue(Uuid::isValid($stored['id']));
        $this->assertSame($path, $stored['filePath']);
        $this->assertSame($query['type'] ?? 'CONTENTS', $stored['type']);
        $this->assertSame([
            'auditLog' => ['userId' => Authentication::USER_ID],
        ] + $context + ['fileName' => $query['fileName'] ?? basename($path)], $stored['metadata']);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['image' => str_replace('app/', '/', $path)],
            json_decode((string) $response->getBody(), true)
        );
        $this->assertFileExists('data/storage/' . $path);
        $image = getimagesize('data/storage/' . $path);
        $this->assertSame($dimensions, [$image[0], $image[1]]);
        $this->assertSame($format === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG, $image[2]);
        if ($format === 'png') {
            $decodedImage = imagecreatefrompng('data/storage/' . $path);
            $this->assertSame(127, (imagecolorat($decodedImage, 0, 0) >> 24) & 0x7f);
        }
        $filesystem = $this->filesystem();
        $listing = $filesystem->listContents('temp');
        $this->assertSame([], $listing->toArray());
    }

    public static function uploads() : array
    {
        return [
            'content default PNG' => ['content', 'png', [], [1000, 500], false],
            'content bounded JPEG' => ['content', 'jpg', [
                'arWidth' => '300', 'arHeight' => '300', 'fileName' => 'cover.jpg', 'type' => 'PRODUCT',
            ], [300, 150], false],
            'generic base64 PNG' => ['generic', 'png', [], [1000, 500], true],
            'generic bounded PNG' => ['generic', 'png', [
                'arWidth' => '240', 'arHeight' => '80', 'fileName' => 'avatar.png', 'type' => 'AVATAR',
            ], [160, 80], false],
            'generic portrait PNG' => ['generic', 'png', [], [250, 500], false, [600, 1200]],
            'content upscaled JPEG' => ['content', 'jpg', [
                'arWidth' => '1500', 'arHeight' => '1000',
            ], [1500, 750], false],
            'generic base64 JPEG' => ['generic', 'jpg', ['arHeight' => '100'], [200, 100], true],
        ];
    }

    public function testRejectsContentUploadsWithoutEditPermissionBeforeSavingFiles() : void
    {
        $files = $this->createMock(FileRepository::class);
        $files->expects($this->never())->method('addNewFile');
        $request = (new ServerRequest([], [], '/images', 'PUT'))
            ->withAttribute('role', RoleBasedAccessControl::fromPermissions('reader', []));

        $this->expectException(InsufficientPrivileges::class);
        $this->handler('content', $files)->handle($request);
    }

    private function handler(string $target, FileRepository $files) : RequestHandlerInterface
    {
        $commands = $this->createStub(CommandBus::class);
        $repository = $this->createStub(GenericRepository::class);
        $filesystem = $this->filesystem();
        if ($target === 'generic') {
            return new GenericUploadImage($commands, $files, $repository, $filesystem, []);
        }

        return new UploadImages(
            $commands,
            $files,
            $this->createStub(CollectionRepository::class),
            $repository,
            $filesystem,
            []
        );
    }
}
