<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\Entity\StaticPage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class Fixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $homepage = new StaticPage();
        $homepage->setName("unittestpage");
        $homepage->setUrl("unittest");
        $homepage->setContent("this is the unit test page");

        $manager->persist($homepage);
        $manager->flush();
    }
}
