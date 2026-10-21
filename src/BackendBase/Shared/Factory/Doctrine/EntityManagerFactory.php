<?php

declare(strict_types=1);

namespace BackendBase\Shared\Factory\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Doctrine\UuidType;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Postgresql as DqlFunctions;
use Symfony\Component\Cache\Adapter\ArrayAdapter as ArrayCache;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter as PhpFileCache;

class EntityManagerFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        mixed $requestedName,
        array|null $options = null,
    ): EntityManager {
        $appConfig = $container->get('config');
        if (! Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }

        $client      = $container->get(Connection::class);
        $doctrineDir = $appConfig['app']['data_dir'] . '/cache/Doctrine';
        $debug       = $appConfig['debug'] === true;
        $config      = new Configuration();
        $driverImpl  = new AnnotationDriver(
            new AnnotationReader(),
            [__DIR__ . '/../../../Infrastructure/Persistence/Doctrine/Entity'],
            true,
        );
        $config->setMetadataCache($this->cache('doctrine_metadata', $debug, $doctrineDir));
        $config->setQueryCache($this->cache('doctrine_queries', $debug, $doctrineDir));
        $config->setProxyDir($doctrineDir . '/Proxies');
        $config->setProxyNamespace($appConfig['doctrine']['namespace-for-generator'] . '\\Proxies');
        $config->addCustomStringFunction(DqlFunctions\JsonGetText::FUNCTION_NAME, DqlFunctions\JsonGetText::class);
        $config->addCustomStringFunction(DqlFunctions\JsonGet::FUNCTION_NAME, DqlFunctions\JsonGet::class);
        $config->setMetadataDriverImpl($driverImpl);
        $config->setResultCache($this->cache('doctrine_results', $debug, $doctrineDir));

        return new EntityManager($client, $config);
    }

    private function cache(string $namespace, bool $debug, string $directory): CacheItemPoolInterface
    {
        if ($debug) {
            return new ArrayCache();
        }

        return new PhpFileCache($namespace, 0, $directory);
    }
}
