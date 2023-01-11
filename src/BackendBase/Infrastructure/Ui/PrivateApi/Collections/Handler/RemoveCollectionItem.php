<?php

declare(strict_types=1);

namespace BackendBase\PrivateApi\Collections\Handler;

use BackendBase\Shared\Services\MessageBus\Interfaces\QueryBus;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RemoveCollectionItem implements RequestHandlerInterface
{
    public function __construct(private QueryBus $queryBus, private array $config)
    {
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
       /* $query = new GetAllUsers(0, 25);
        $users = $this->queryBus->handle($query);
        */
        $session = [
            'access_token' => '',
            'will_expire' => '',
        ];

        return new JsonResponse(['session' => $session], 201);
    }
}
