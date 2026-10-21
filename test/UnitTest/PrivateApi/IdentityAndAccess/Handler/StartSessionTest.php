<?php

declare(strict_types=1);

namespace UnitTest\PrivateApi\IdentityAndAccess\Handler;

use BackendBase\Domain\IdentityAndAccess\Exception\LoginAttemptLimitExceeded;
use BackendBase\Domain\User\Exception\UserNotFound;
use BackendBase\Domain\User\Interfaces\UserRepository;
use BackendBase\Infrastructure\Persistence\Doctrine\Repository\RolesRepository;
use BackendBase\PrivateApi\IdentityAndAccess\Handler\StartSession;
use BackendBase\Shared\Middleware\PrivateApiAuthorizationMiddleware;
use DateTimeImmutable;
use Fixtures\Authentication;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RateLimit\RedisRateLimiter;
use Redis;

use function base64_decode;
use function explode;
use function hash;
use function hash_hmac;
use function json_decode;
use function strtr;
use function time;

final class StartSessionTest extends TestCase
{
    public function testCreatesASignedTwelveHourTokenAndReturnsPublicUserData() : void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->once())->method('getUserByEmail')
            ->with($this->callback(static function ($email) : bool {
                return $email->toString() === Authentication::EMAIL;
            }))->willReturn(Authentication::user());
        $roles = $this->createMock(RolesRepository::class);
        $roles->expects($this->once())->method('getRolePermissionsByRoleName')
            ->with('editor')->willReturn(['contents.cms.edit']);
        $roles->expects($this->once())->method('getRolePermissionsByRoleNameForUser')
            ->with(Authentication::USER_ID)->willReturn(['contents.cms.edit']);
        $before = time();

        $response = $this->handler($users, $roles)->handle($this->request(Authentication::PASSWORD));

        $after = time();
        $data = json_decode((string) $response->getBody(), true);
        [$header, $payload, $signature] = explode('.', $data['accessToken']);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['typ' => 'JWT', 'alg' => 'HS256'],
            json_decode(base64_decode(strtr($header, '-_', '+/')), true)
        );
        $this->assertSame(
            hash_hmac('sha256', $header . '.' . $payload, Authentication::SIGNING_KEY, true),
            base64_decode(strtr($signature, '-_', '+/'))
        );
        $this->assertSame('storage', $claims['iss']);
        $this->assertSame(Authentication::USER_ID, $claims['userId']);
        $this->assertSame('editor', $claims['role']);
        $this->assertIsInt($claims['iat']);
        $this->assertIsInt($claims['nbf']);
        $this->assertIsInt($claims['exp']);
        $this->assertGreaterThanOrEqual($before, $claims['iat']);
        $this->assertLessThanOrEqual($after, $claims['iat']);
        $this->assertSame($claims['iat'], $claims['nbf']);
        $this->assertSame(43200, $claims['exp'] - $claims['iat']);
        $this->assertSame($claims['iat'], (new DateTimeImmutable($data['createdAt']))->getTimestamp());
        $this->assertSame($claims['exp'], (new DateTimeImmutable($data['willExpireAt']))->getTimestamp());
        $this->assertSame([
            'firstName' => 'Test', 'lastName' => 'Editor', 'email' => Authentication::EMAIL,
            'role' => 'editor', 'permissions' => ['contents.cms.edit'],
        ], $data['userData']);
        $this->assertArrayNotHasKey('passwordHash', $data['userData']);

        $protectedHandler = $this->createMock(RequestHandlerInterface::class);
        $protectedHandler->expects($this->once())->method('handle')
            ->with($this->callback(static function (ServerRequestInterface $request): bool {
                return $request->getAttribute('loggedUserId') === Authentication::USER_ID;
            }))->willReturn(new EmptyResponse());
        $protectedRequest = (new ServerRequest([], [], '/cms/contents', 'GET'))
            ->withHeader('Authorization', 'Bearer ' . $data['accessToken']);
        $middleware = new PrivateApiAuthorizationMiddleware($roles);
        $protectedResponse = $middleware->process($protectedRequest, $protectedHandler);

        $this->assertSame(204, $protectedResponse->getStatusCode());
    }

    public function testRejectsAnIncorrectPasswordBeforeLoadingPermissions() : void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->once())->method('getUserByEmail')->willReturn(Authentication::user());
        $roles = $this->createMock(RolesRepository::class);
        $roles->expects($this->never())->method('getRolePermissionsByRoleName');

        $this->expectException(UserNotFound::class);
        $this->expectExceptionMessage('Invalid username and/or password');
        $this->handler($users, $roles)->handle($this->request('wrong-password'));
    }

    public function testRejectsAnExceededLoginLimitBeforeLookingUpTheUser() : void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->never())->method('getUserByEmail');
        $roles = $this->createMock(RolesRepository::class);
        $roles->expects($this->never())->method('getRolePermissionsByRoleName');

        $this->expectException(LoginAttemptLimitExceeded::class);
        $this->expectExceptionMessage('You are allowed to attempt login 10 per hour.');
        $this->handler($users, $roles, 10)->handle($this->request(Authentication::PASSWORD));
    }

    private function request(string $password) : ServerRequest
    {
        return (new ServerRequest([], [], '/sessions', 'POST'))->withParsedBody([
            'email' => Authentication::EMAIL, 'password' => $password,
        ]);
    }

    private function handler(UserRepository $users, RolesRepository $roles, int $attempts = 0) : StartSession
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects($this->once())->method('get')
            ->with(hash('sha256', Authentication::EMAIL) . ':3600')->willReturn((string) $attempts);
        if ($attempts < 10) {
            $redis->expects($this->once())->method('incr')->willReturn(1);
            $redis->expects($this->once())->method('expire')->willReturn(true);
        }
        if ($attempts >= 10) {
            $redis->expects($this->never())->method('incr');
        }

        return new StartSession($users, $roles, new RedisRateLimiter($redis));
    }
}
