<?php

declare(strict_types=1);

namespace BackendBase\PrivateApi\Contents\Handler;

use BackendBase\Domain\Collections\Interfaces\CollectionRepository;
use BackendBase\Domain\IdentityAndAccess\Exception\InsufficientPrivileges;
use BackendBase\Domain\IdentityAndAccess\Model\Permissions;
use BackendBase\Infrastructure\Persistence\Doctrine\Repository\FileRepository;
use BackendBase\Infrastructure\Persistence\Doctrine\Repository\GenericRepository;
use BackendBase\Shared\Services\MessageBus\Interfaces\CommandBus;
use Cocur\Slugify\Slugify;
use Intervention\Image\ImageManager;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Permissions\Rbac\Role;
use League\Flysystem\Filesystem;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use function array_key_exists;
use function basename;
use function str_replace;

class UploadImages implements RequestHandlerInterface
{
    private const RESULT_ROWS_LIMIT = 25;
    private const ULID_LOWERCASE    = true;

    private array $config;
    private Filesystem $fileSystem;
    private FileRepository $fileRepository;
    private CommandBus $commandBus;
    private CollectionRepository $collectionRepository;
    private GenericRepository $genericRepository;
    private Slugify $slugifier;

    public function __construct(
        CommandBus $commandBus,
        FileRepository $fileRepository,
        CollectionRepository $collectionRepository,
        GenericRepository $genericRepository,
        Filesystem $fileSystem,
        array $config
    ) {
        $this->config               = $config;
        $this->commandBus           = $commandBus;
        $this->fileSystem           = $fileSystem;
        $this->fileRepository       = $fileRepository;
        $this->genericRepository    = $genericRepository;
        $this->collectionRepository = $collectionRepository;
        $this->slugifier            = new Slugify(['rulesets' => ['default', 'turkish']]);
    }

    private static function findExtension(string $mimetype) : string
    {
        return [
            'image/jpeg' => 'jpg',
            'image/pipeg' => 'jfif',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ][$mimetype];
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        /**
         * @var Role
         */
        $role = $request->getAttribute('role');
        if ($role->hasPermission(Permissions\Contents::CMS_EDIT) === false) {
            throw InsufficientPrivileges::create('You dont have privilege to upload an image for contents');
        }
        $loggedUserId      = $request->getAttribute('loggedUserId');
        $queryParams       = $request->getQueryParams();
        $aspectRatioWidth  =  array_key_exists('arWidth', $queryParams) ? (int) $queryParams['arWidth'] : null;
        $aspectRatioHeight = array_key_exists('arHeight', $queryParams)  ? (int) $queryParams['arHeight'] : 500;
        $key               = 'images';
        $type              = $queryParams['type'] ?? 'CONTENTS';

        $uploadedFile = $request->getAttribute('uploadedFilePath');
        $contentId    = $request->getAttribute('contentId');
        $fileId       = Uuid::uuid4()->toString();
        $filePath     =  'app/images/content/' . $contentId . '/' . $fileId . '.' . self::findExtension($this->fileSystem->mimeType($uploadedFile));

        $this->fileSystem->move($uploadedFile, $filePath);
        $imageManager  = ImageManager::gd(autoOrientation: false, decodeAnimation: false);
        $imageContents = $this->fileSystem->read($filePath);
        $img           = $imageManager->read($imageContents);
        $img->scale($aspectRatioWidth, $aspectRatioHeight);
        $encodedImage = $img->encodeByPath($filePath, quality: 90);
        $this->fileSystem->write($filePath, (string) $encodedImage);
        $fileData = [
            'id' =>$fileId ,
            'filePath' => $filePath,
            'type' => $type,
            'metadata' => [
                'auditLog' => ['userId' => $loggedUserId],
                'contentId' => $contentId,
                'fileName' => $queryParams['fileName'] ?? basename($filePath),
            ],
        ];
        $this->fileRepository->addNewFile($fileData);

        return new JsonResponse(['image' => str_replace('app/', '/', $filePath) ], 201);
    }
}
