<?php

declare(strict_types=1);

namespace BackendBase\Shared\ValueObject;

use const DIRECTORY_SEPARATOR;
use function pathinfo;

class File
{
    private array $pathInfo;

    public function __construct(string $filePath, private LocalizedDateTime $createdAt)
    {
        $this->pathInfo  = pathinfo($filePath);
    }

    public function filePath() : string
    {
        return $this->pathInfo['dirname'] . DIRECTORY_SEPARATOR . $this->pathInfo['basename'];
    }

    public function dirname() : string
    {
        return $this->pathInfo['dirname'];
    }

    public function basename() : string
    {
        return $this->pathInfo['basename'];
    }

    public function extension() : string
    {
        return $this->pathInfo['extension'];
    }

    public function createdAt() : LocalizedDateTime
    {
        return $this->createdAt;
    }
}
