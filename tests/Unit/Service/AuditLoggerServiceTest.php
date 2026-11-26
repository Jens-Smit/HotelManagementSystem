<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AuditLoggerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLoggerServiceTest extends TestCase
{
    public function testLogSecurityEventIncludesRequestContext(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with($this->callback(function (string $message) {
                $data = json_decode($message, true);
                return $data['event'] === 'LOGIN_BLOCK'
                    && $data['ip'] === '203.0.113.5'
                    && $data['path'] === '/api/login'
                    && isset($data['timestamp']);
            }), ['channel' => 'security']);

        $request = Request::create('/api/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_USER_AGENT' => 'Test-Agent',
        ]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $service = new AuditLoggerService($logger, $requestStack);
        $service->logSecurityEvent('LOGIN_BLOCK', ['versuch' => 1]);
    }

    public function testLogAuthAttemptBuildsExpectedPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with($this->callback(function (string $message) {
                $data = json_decode($message, true);
                return $data['event'] === 'AUTH_ATTEMPT'
                    && $data['email'] === 'user@example.com'
                    && $data['success'] === true;
            }), ['channel' => 'security']);

        $service = new AuditLoggerService($logger, new RequestStack());
        $service->logAuthAttempt('user@example.com', true);
    }

    public function testLogUnauthorizedAccessCarriesUserId(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with($this->stringContains('"event":"UNAUTHORIZED_ACCESS"'));

        $service = new AuditLoggerService($logger, new RequestStack());
        $service->logUnauthorizedAccess('booking/xyz', 42);
    }

    public function testLogSuspiciousActivityMergesDetails(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with($this->callback(function (string $message) {
                $data = json_decode($message, true);
                return $data['activity'] === 'bruteforce'
                    && $data['attempts'] === 10
                    && $data['event'] === 'SUSPICIOUS_ACTIVITY';
            }));

        $service = new AuditLoggerService($logger, new RequestStack());
        $service->logSuspiciousActivity('bruteforce', ['attempts' => 10]);
    }
}
