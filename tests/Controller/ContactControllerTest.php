<?php
// tests/Controller/ContactControllerTest.php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;

class ContactControllerTest extends WebTestCase
{
    public function testSubmitContactSuccess(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Max Mustermann',
                'email' => 'max@example.com',
                'subject' => 'Test',
                'message' => 'Hallo, dies ist ein Test.'
            ])
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('Ihre Nachricht wurde erfolgreich gesendet.', $data['message']);
    }

    public function testSubmitContactFailsWithValidationErrors(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Max Mustermann',
                'email' => 'invalid-email',
                'subject' => '',
                'message' => ''
            ])
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);

        $errorsKeys = array_keys($data['errors'] ?? []);
        $this->assertTrue(in_array('email', $errorsKeys, true) || in_array('[email]', $errorsKeys, true), 'errors enthält keinen Schlüssel für email');
        $this->assertTrue(in_array('subject', $errorsKeys, true) || in_array('[subject]', $errorsKeys, true), 'errors enthält keinen Schlüssel für subject');
        $this->assertTrue(in_array('message', $errorsKeys, true) || in_array('[message]', $errorsKeys, true), 'errors enthält keinen Schlüssel für message');
    }

    public function testSubmitContactFailsWithInvalidJson(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{invalid json}'
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('Ungültige JSON-Daten.', $data['message']);
    }

    public function testSubmitContactFailsOnMailerException(): void
    {
        $client = static::createClient();

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->method('send')
            ->willThrowException(new \RuntimeException('SMTP connection failed'));

        $client->getContainer()->set(MailerInterface::class, $mailerMock);

        $client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Max Mustermann',
                'email' => 'max@example.com',
                'subject' => 'Test',
                'message' => 'Hallo, dies ist ein Test.'
            ])
        );

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Beim Senden der Nachricht ist ein Fehler aufgetreten.', $data['message']);
    }
}
