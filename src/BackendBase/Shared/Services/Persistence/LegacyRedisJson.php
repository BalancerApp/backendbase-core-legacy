<?php

declare(strict_types=1);

namespace BackendBase\Shared\Services\Persistence;

use Redislabs\Interfaces\CommandInterface;
use Redislabs\Module\RedisJson\Command\Get;
use Redislabs\Module\RedisJson\Command\MultipleGet;
use Redislabs\Module\ReJSON\ReJSON;

use function array_map;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/** Preserves object decoding and positional MGET results for existing callers. */
final class LegacyRedisJson extends ReJSON
{
    public function get(mixed ...$arguments): mixed
    {
        $command = Get::createCommandWithArguments($arguments);
        $result  = $this->rawResponse($command);

        return self::decode($result);
    }

    /** @return list<mixed> */
    public function mget(mixed ...$arguments): array
    {
        $command = MultipleGet::createCommandWithArguments($arguments);
        $results = $this->rawResponse($command);

        return array_map(self::decode(...), $results);
    }

    private function rawResponse(CommandInterface $command): mixed
    {
        $name      = $command->getCommand();
        $arguments = $command->getArguments();

        return $this->raw($name, ...$arguments);
    }

    private static function decode(string|false|null $result): mixed
    {
        if ($result === false || $result === null) {
            return null;
        }

        return json_decode($result, false, 512, JSON_THROW_ON_ERROR);
    }
}
