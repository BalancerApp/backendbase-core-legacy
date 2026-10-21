<?php

declare(strict_types=1);

namespace UnitTest\Shared\Middleware;

use BackendBase\Domain\IdentityAndAccess\Exception\AuthenticationFailed;
use BackendBase\Infrastructure\Persistence\Doctrine\Repository\RolesRepository;
use BackendBase\Shared\Middleware\PrivateApiAuthorizationMiddleware;
use Fixtures\Authentication;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function time;

final class PrivateApiAuthorizationMiddlewareTest extends TestCase
{
    public function testPassesTheAuthenticatedUserAndCurrentPermissionsToTheHandler() : void
    {
        $roles = $this->createMock(RolesRepository::class);
        $roles->expects($this->once())->method('getRolePermissionsByRoleNameForUser')
            ->with(Authentication::USER_ID)->willReturn(['contents.cms.edit']);
        $response = new EmptyResponse();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')
            ->with($this->callback(static function (ServerRequestInterface $request) : bool {
                $role = $request->getAttribute('role');

                return $request->getAttribute('loggedUserId') === Authentication::USER_ID
                    && $role->getName() === 'editor'
                    && $role->hasPermission('contents.cms.edit')
                    && ! $role->hasPermission('users.delete');
            }))->willReturn($response);
        $request = (new ServerRequest([], [], '/cms/contents', 'GET'))
            ->withHeader('Authorization', 'Bearer ' . Authentication::token());

        $this->assertSame($response, (new PrivateApiAuthorizationMiddleware($roles))->process($request, $handler));
    }

    #[DataProvider('invalidTokens')]
    public function testRejectsInvalidTokensWithoutCallingRepositoriesOrTheHandler(string $token) : void
    {
        $roles = $this->createMock(RolesRepository::class);
        $roles->expects($this->never())->method('getRolePermissionsByRoleNameForUser');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');
        $request = (new ServerRequest([], [], '/cms/contents', 'GET'))
            ->withHeader('Authorization', $token);

        $this->expectException(AuthenticationFailed::class);
        (new PrivateApiAuthorizationMiddleware($roles))->process($request, $handler);
    }

    public static function invalidTokens() : array
    {
        return [
            'missing token' => [''],
            'malformed token' => ['Bearer not-a-jwt'],
            'wrong issuer' => ['Bearer ' . Authentication::token(['iss' => 'another-service'])],
            'expired token' => ['Bearer ' . Authentication::token(['exp' => time() - 3600])],
            'not yet valid token' => ['Bearer ' . Authentication::token(['nbf' => time() + 3600])],
            'future issue time' => ['Bearer ' . Authentication::token(['iat' => time() + 3600])],
            'invalid issue time' => ['Bearer ' . Authentication::token(['iat' => 'not-a-timestamp'])],
            'missing user ID' => ['Bearer ' . Authentication::token(['userId' => null])],
            'empty user ID' => ['Bearer ' . Authentication::token(['userId' => ''])],
            'array user ID' => ['Bearer ' . Authentication::token(['userId' => ['invalid']])],
            'missing role' => ['Bearer ' . Authentication::token(['role' => null])],
            'empty role' => ['Bearer ' . Authentication::token(['role' => ''])],
            'array role' => ['Bearer ' . Authentication::token(['role' => ['editor']])],
        ];
    }

    #[DataProvider('publicRequests')]
    public function testPassesPublicRequestsThroughWithoutAToken(string $method, string $path) : void
    {
        $request = new ServerRequest([], [], $path, $method);
        $roles = $this->createMock(RolesRepository::class);
        $roles->expects($this->never())->method('getRolePermissionsByRoleNameForUser');
        $response = new EmptyResponse();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);

        $this->assertSame($response, (new PrivateApiAuthorizationMiddleware($roles))->process($request, $handler));
    }

    public static function publicRequests() : array
    {
        return [['OPTIONS', '/cms/contents'], ['POST', '/sessions'], ['GET', '/_reset']];
    }
}
