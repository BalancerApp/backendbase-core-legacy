<?php

declare(strict_types=1);

namespace UnitTest\Shared\Factory;

use BackendBase\Shared\Factory\Filesystem\LocalFactory;
use Fixtures\ImageUploadTestCase;
use Laminas\ServiceManager\ServiceManager;
use League\Flysystem\Filesystem;

use function fileperms;
use function umask;

final class LocalFactoryTest extends ImageUploadTestCase
{
    public function testPreservesPublicDirectoryPermissionsAndTheConfiguredStorageRoot(): void
    {
        $root       = $this->temporaryDirectory . '/custom-storage';
        $container  = new ServiceManager(['services' => ['config' => ['app' => ['storage-dir' => $root]]]]);
        $filesystem = (new LocalFactory())($container, Filesystem::class);

        $filesystem->write('incoming/file.txt', 'test contents');
        $filesystem->move('incoming/file.txt', 'published/file.txt');

        $this->assertSame('test contents', $filesystem->read('published/file.txt'));
        $this->assertSame('text/plain', $filesystem->mimeType('published/file.txt'));
        $this->assertFalse($filesystem->fileExists('incoming/file.txt'));
        $this->assertSame(0755 & ~umask(), fileperms($root) & 0777);
        $this->assertSame(0755 & ~umask(), fileperms($root . '/published') & 0777);
    }
}
