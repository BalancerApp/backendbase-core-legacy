<?php

declare(strict_types=1);

namespace BackendBase\Shared\Middleware;

use BackendBase\Domain\IdentityAndAccess\Exception\AuthenticationFailed;
use BackendBase\Infrastructure\Persistence\Doctrine\Repository\RolesRepository;
use BackendBase\Shared\Services\RoleBasedAccessControl;
use DateTimeImmutable;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Validator;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use function is_string;
use function str_replace;

final class PrivateApiAuthorizationMiddleware implements MiddlewareInterface
{
    private RolesRepository $rolesRepository;

    public function __construct(RolesRepository $rolesRepository)
    {
        $this->rolesRepository = $rolesRepository;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS' || $request->getUri()->getPath() === '/_reset' || ($request->getMethod() === 'POST' && $request->getUri()->getPath() === '/sessions')) {
            return $handler->handle($request);
        }
        $authHeader = str_replace('Bearer ', '', $request->getHeaderLine('Authorization'));
        if (empty($authHeader)) {
            throw AuthenticationFailed::create('Authentication failed.');
        }
        try {
            $token = (new Parser(new JoseEncoder()))->parse((string) $authHeader);
            $clock = new class implements ClockInterface {
                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable();
                }
            };
            $validator = new Validator();
            if ($validator->validate($token, new IssuedBy('storage'), new LooseValidAt($clock)) === false) {
                throw AuthenticationFailed::create('Authentication failed. Invalid Token or token expired.');
            }
            $claims   = $token->claims();
            $userId   = $claims->get('userId');
            $roleName = $claims->get('role');
            if (! is_string($userId) || $userId === '' || ! is_string($roleName) || $roleName === '') {
                throw AuthenticationFailed::create('Authentication failed. Invalid user or role claim.');
            }

            $role = RoleBasedAccessControl::fromPermissions($roleName, $this->rolesRepository->getRolePermissionsByRoleNameForUser($userId));

            $request = $request
                ->withAttribute('loggedUserId', $userId);
            $request = $request
                ->withAttribute('role', $role);
        } catch (Throwable $e) {
            throw AuthenticationFailed::create('Authentication failed.' . $e->getMessage());
        }

        return $handler->handle($request);
    }
}
