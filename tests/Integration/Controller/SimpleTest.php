<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SimpleTest extends WebTestCase
{
    public function testHealthCheck(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertStringContainsString('healthy', $client->getResponse()->getContent());
    }
}