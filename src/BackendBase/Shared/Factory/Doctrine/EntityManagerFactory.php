<?php

declare(strict_types=1);

namespace BackendBase\Shared\Factory\Doctrine;

use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Doctrine\UuidType;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Postgresql as DqlFunctions;
use Symfony\Component\Cache\Adapter\ArrayAdapter as ArrayCache;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter as PhpFileCache;

class EntityManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): EntityManager
    {
        $appConfig = $container->get('config');
        if ($appConfig['debug'] === true) {
            $cache = new ArrayCache();
            $resultCache = new ArrayCache();
            $metadataCache = new ArrayCache();
        } else {
            $cache = new PhpFileCache('doctrine_queries');
            $resultCache = new PhpFileCache('doctrine_results');
            $metadataCache = new PhpFileCache('doctrine_metadata');
        }

        Type::addType('uuid', UuidType::class);
        $client      = $container->get(Connection::class);
        $doctrineDir = $appConfig['app']['data_dir'] . '/cache/Doctrine';
        $config      = new Configuration();
        $driverImpl  = $config->newDefaultAnnotationDriver(['src/Infrastructure/Persistence/Doctrine/Entity'], false);
        $config->setMetadataCache($cache);
        $config->setQueryCache($cache);
        $config->setProxyDir($doctrineDir . '/Proxies');
        $config->setProxyNamespace($appConfig['doctrine']['namespace-for-generator'] . '\\Proxies');
        $config->addCustomStringFunction(DqlFunctions\JsonGetText::FUNCTION_NAME, DqlFunctions\JsonGetText::class);
        $config->addCustomStringFunction(DqlFunctions\JsonGet::FUNCTION_NAME, DqlFunctions\JsonGet::class);
        $config->setMetadataDriverImpl($driverImpl);
        $config->setResultCache($resultCache);

        return EntityManager::create($client, $config);
    }
}
