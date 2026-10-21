<?php

declare(strict_types=1);

namespace Fixtures;

use BackendBase\Shared\Factory\Filesystem\LocalFactory;
use BackendBase\Shared\Middleware\CatchUploadedFile;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\ServiceManager\ServiceManager;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function base64_encode;
use function bin2hex;
use function chdir;
use function file_get_contents;
use function getcwd;
use function imagealphablending;
use function imagecolorallocatealpha;
use function imagecreatetruecolor;
use function imagefill;
use function imagejpeg;
use function imagepng;
use function imagesavealpha;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

abstract class ImageUploadTestCase extends TestCase
{
    private string $originalDirectory;
    protected string $temporaryDirectory;

    protected function setUp() : void
    {
        $this->originalDirectory = getcwd();
        $this->temporaryDirectory = sys_get_temp_dir() . '/backendbase-images-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory . '/data/storage/temp', 0700, true);
        chdir($this->temporaryDirectory);
    }

    protected function tearDown() : void
    {
        chdir($this->originalDirectory);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->temporaryDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
                continue;
            }
            unlink($file->getPathname());
        }
        rmdir($this->temporaryDirectory);
    }

    protected function filesystem() : Filesystem
    {
        $container = new ServiceManager(['services' => ['config' => [
            'app' => ['storage-dir' => $this->temporaryDirectory . '/data/storage'],
        ]]]);

        return (new LocalFactory())($container, Filesystem::class);
    }

    protected function upload(
        RequestHandlerInterface $handler,
        array $attributes,
        array $query,
        string $format,
        bool $base64,
        array $sourceDimensions = [1200, 600]
    ) : ResponseInterface {
        $source = $this->temporaryDirectory . '/source.' . $format;
        $image = imagecreatetruecolor($sourceDimensions[0], $sourceDimensions[1]);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 32, 64, 128, 127));
        if ($format === 'png') {
            imagepng($image, $source);
        }
        if ($format === 'jpg') {
            imagejpeg($image, $source);
        }
        $contents = file_get_contents($source);
        if ($base64) {
            $mediaType = $format === 'jpg' ? 'jpeg' : $format;
            $contents = 'data:image/' . $mediaType . ';base64,' . base64_encode($contents);
        }
        $body = new Stream('php://temp', 'w+');
        $body->write($contents);
        $body->rewind();
        $request = (new ServerRequest([], [], '/images', 'PUT'))
            ->withBody($body)
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withQueryParams($query);
        if ($base64) {
            $request = $request->withHeader('Content-Transfer-Encoding', 'Base64');
        }
        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return (new CatchUploadedFile())->process($request, $handler);
    }
}
