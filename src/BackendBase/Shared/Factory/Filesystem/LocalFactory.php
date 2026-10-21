<?php

declare(strict_types=1);

namespace BackendBase\Shared\Factory\Filesystem;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

final class LocalFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config     = $container->get('config');
        $visibility = new PortableVisibilityConverter(defaultForDirectories: Visibility::PUBLIC);
        $adapter    = new LocalFilesystemAdapter($config['app']['storage-dir'], $visibility);

        return new Filesystem($adapter);
    }
}
