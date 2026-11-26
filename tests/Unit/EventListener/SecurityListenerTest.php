<?php
declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\ExceptionListener;
use App\EventListener\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SecurityListenerTest extends TestCase
{
    public function testExceptionListenerHidesDetailsInProd(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/secret', 'GET');

        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('Interne Details die nicht geleakt werden dürfen')
        );

        $listener = new ExceptionListener($logger, 'prod');
        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Ein Fehler ist aufgetreten', $data['error']);
        $this->assertArrayNotHasKey('trace', $data, 'In prod dürfen keine Stacktraces geleakt werden');
    }

    public function testExceptionListenerShowsDetailsInDev(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/dev', 'GET');

        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('Sichtbare Meldung')
        );

        $listener = new ExceptionListener($logger, 'dev');
        $listener->onKernelException($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('Sichtbare Meldung', $data['error']);
        $this->assertArrayHasKey('trace', $data);
    }

    public function testExceptionListenerLogsRequestContext(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Exception occurred',
            self::callback(fn (array $context) => $context['path'] === '/api/x' && $context['method'] === 'POST')
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/x', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.7']);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new \RuntimeException('boom'));

        (new ExceptionListener($logger, 'prod'))->onKernelException($event);
        $this->addToAssertionCount(1);
    }

    public function testSecurityHeadersListenerRedirectsHttpInProd(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://example.com/api/rooms', 'GET');

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response('ok'));

        (new SecurityHeadersListener('prod'))->onKernelResponse($event);

        $this->assertSame(301, $event->getResponse()->getStatusCode());
        $this->assertSame('https://example.com/api/rooms', $event->getResponse()->headers->get('Location'));
    }

    public function testSecurityHeadersListenerSetsHstsOnlyOnHttps(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('https://example.com/api/rooms', 'GET');

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response('ok'));

        (new SecurityHeadersListener('prod'))->onKernelResponse($event);

        $this->assertNotNull($event->getResponse()->headers->get('Strict-Transport-Security'));
    }

    public function testSecurityHeadersListenerSkipsSubRequests(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://example.com/', 'GET');

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, new Response('ok'));

        (new SecurityHeadersListener('prod'))->onKernelResponse($event);

        $this->assertSame(200, $event->getResponse()->getStatusCode(), 'Sub-Requests dürfen nicht umgeleitet werden');
    }

    public function testSubscribedEventsAreConfigured(): void
    {
        $this->assertArrayHasKey('kernel.exception', ExceptionListener::getSubscribedEvents());
        $this->assertArrayHasKey('kernel.response', SecurityHeadersListener::getSubscribedEvents());
    }
}
