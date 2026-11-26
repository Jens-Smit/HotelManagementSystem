<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\AppCustomAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class AppCustomAuthenticatorTest extends TestCase
{
    public function testSupportsOnlyPostRequestsToLoginRoute(): void
    {
        $authenticator = new AppCustomAuthenticator($this->createMock(JWTTokenManagerInterface::class));

        $this->assertTrue($authenticator->supports(Request::create('/api/login', 'POST')));
        $this->assertFalse($authenticator->supports(Request::create('/api/login', 'GET')));
        $this->assertFalse($authenticator->supports(Request::create('/api/bookings', 'POST')));
    }

    public function testAuthenticateBuildsPassportFromCredentials(): void
    {
        $authenticator = new AppCustomAuthenticator($this->createMock(JWTTokenManagerInterface::class));

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => 'user@example.com', 'password' => 'secret']));

        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(Passport::class, $passport);
        $this->assertSame(
            'user@example.com',
            $passport->getBadge(UserBadge::class)->getUserIdentifier()
        );
    }

    public function testAuthenticateRejectsMalformedPayload(): void
    {
        $authenticator = new AppCustomAuthenticator($this->createMock(JWTTokenManagerInterface::class));

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => 'user@example.com']));

        $this->expectException(AuthenticationException::class);

        $authenticator->authenticate($request);
    }

    public function testOnAuthenticationSuccessReturnsJwtResponse(): void
    {
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->method('create')->willReturn('jwt-token');

        $authenticator = new AppCustomAuthenticator($jwtManager);

        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user@example.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $response = $authenticator->onAuthenticationSuccess(Request::create('/api/login'), $token, 'api');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(['token' => 'jwt-token'], json_decode($response->getContent(), true));
    }

    public function testOnAuthenticationFailureReturns401(): void
    {
        $authenticator = new AppCustomAuthenticator($this->createMock(JWTTokenManagerInterface::class));

        $response = $authenticator->onAuthenticationFailure(
            Request::create('/api/login'),
            new AuthenticationException('Bad credentials')
        );

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Authentication failed', $data['error']);
    }
}
