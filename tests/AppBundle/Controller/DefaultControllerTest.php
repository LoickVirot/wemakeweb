<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/unittest');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains(
            'this is the unit test page',
            $client->getResponse()->getContent()
        );
    }
}
