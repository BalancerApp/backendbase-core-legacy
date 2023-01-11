<?php

declare(strict_types=1);

namespace BackendBase\Shared\Factory\Doctrine;

use Symfony\Component\Cache\Adapter\ArrayAdapter as ArrayCache;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter as PhpFileCache;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Ramsey\Uuid\Doctrine\UuidType;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Postgresql as DqlFunctions;

final class EntityManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null) : EntityManager
    {
        $appConfig = $container->get('config');
        if ($appConfig['debug'] === true) {
            $cache = DoctrineProvider::wrap(new ArrayCache());
        } else {
            $cache = DoctrineProvider::wrap(new PhpFileCache());
        }
        Type::addType('uuid', UuidType::class);
        $client      = $container->get(Connection::class);
        $doctrineDir = $appConfig['app']['data_dir'] . '/cache/Doctrine';
        $config      = new Configuration();
        $driverImpl  = $this->newDefaultAnnotationDriver('src/Infrastructure/Persistence/Doctrine/Entity');
        $config->setMetadataCacheImpl($cache);
        $config->setProxyDir($doctrineDir . '/Proxies');
        $config->setProxyNamespace($appConfig['doctrine']['namespace-for-generator'] . '\\Proxies');
        $config->setQueryCacheImpl($cache);
        $config->addCustomStringFunction(DqlFunctions\JsonGetText::FUNCTION_NAME, DqlFunctions\JsonGetText::class);
        $config->addCustomStringFunction(DqlFunctions\JsonGet::FUNCTION_NAME, DqlFunctions\JsonGet::class);
        $config->setMetadataDriverImpl($driverImpl);

        return EntityManager::create($client, $config);
    }
       private function newDefaultAttributeDriver($paths = []): AttributeDriver
    {
           return new AttributeDriver($paths);
    }
}
