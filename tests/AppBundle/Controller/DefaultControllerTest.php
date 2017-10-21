<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    private $client;
    private $em;
    private $container;

    public function setUp() {
        parent::setUp();

        $this->client = static::createClient(array('environment' => 'test'));
        $this->container = $this->client->getContainer();
        $this->em = $this->container->get('doctrine')->getManager();
    }

    public function testIndex()
    {
        $crawler = $this->client->request('GET', '/unittest');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains(
            'this is the unit test page',
            $this->client->getResponse()->getContent()
        );
    }

    public function testStaticPageUrlGeneration()
    {
        $twig = $this->container->get('twig');
        $globals = $twig->getGlobals();
        $url = $globals['prefix_static_link'] . "unittest";

        $this->assertEquals("/unittest", $url);
        $crawler = $this->client->request('GET', $url);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains(
            'this is the unit test page',
            $this->client->getResponse()->getContent()
        );
    }
}
