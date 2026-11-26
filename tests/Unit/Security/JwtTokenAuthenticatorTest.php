<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\JwtTokenAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtTokenAuthenticatorTest extends TestCase
{
    public function testSupportsRequiresBearerCookie(): void
    {
        $authenticator = new JwtTokenAuthenticator(
            $this->createMock(JWTTokenManagerInterface::class),
            $this->createMock(UserProviderInterface::class)
        );

        $withCookie = Request::create('/api/rooms');
        $withCookie->cookies->set('BEARER', 'token');

        $this->assertTrue($authenticator->supports($withCookie));
        $this->assertFalse($authenticator->supports(Request::create('/api/rooms')));
    }

    public function testAuthenticateBuildsPassportFromValidToken(): void
    {
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->method('parse')->willReturn(['username' => 'user@example.com', 'exp' => time() + 3600]);

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPassword('hashed');

        $userProvider = $this->createMock(UserProviderInterface::class);
        $userProvider->method('loadUserByIdentifier')->willReturn($user);

        $authenticator = new JwtTokenAuthenticator($jwtManager, $userProvider);

        $request = Request::create('/api/rooms');
        $request->cookies->set('BEARER', 'valid-token');

        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertSame('user@example.com', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateRejectsInvalidToken(): void
    {
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->method('parse')->willThrowException(new \RuntimeException('signature invalid'));

        $authenticator = new JwtTokenAuthenticator($jwtManager, $this->createMock(UserProviderInterface::class));

        $request = Request::create('/api/rooms');
        $request->cookies->set('BEARER', 'invalid-token');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Ungültiger oder abgelaufener Token.');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsTokenWithoutUsername(): void
    {
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->method('parse')->willReturn(['exp' => time() + 3600]);

        $authenticator = new JwtTokenAuthenticator($jwtManager, $this->createMock(UserProviderInterface::class));

        $request = Request::create('/api/rooms');
        $request->cookies->set('BEARER', 'token-without-username');

        $this->expectException(AuthenticationException::class);

        $authenticator->authenticate($request);
    }

    public function testOnAuthenticationFailureReturns401(): void
    {
        $authenticator = new JwtTokenAuthenticator(
            $this->createMock(JWTTokenManagerInterface::class),
            $this->createMock(UserProviderInterface::class)
        );

        $response = $authenticator->onAuthenticationFailure(
            Request::create('/api/rooms'),
            new AuthenticationException('expired')
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Authentication failed', json_decode($response->getContent(), true)['error']);
    }

    public function testStartReturns401(): void
    {
        $authenticator = new JwtTokenAuthenticator(
            $this->createMock(JWTTokenManagerInterface::class),
            $this->createMock(UserProviderInterface::class)
        );

        $response = $authenticator->start(Request::create('/api/rooms'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Authentifizierung erforderlich', json_decode($response->getContent(), true)['error']);
    }
}
