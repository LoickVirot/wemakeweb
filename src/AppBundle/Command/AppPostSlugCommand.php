<?php

namespace AppBundle\Command;

use AppBundle\Utils\UrlUtils;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppPostSlugCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:post:slug')
            ->setDescription('regenerate slug for all article with no slug')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        $postsToSlug = $em->getRepository('AppBundle:Post')->findBy([
            'slug' => ""
        ]);

        foreach ($postsToSlug as $post) {
            $post->setSlug(uniqid() . '-' . UrlUtils::slugify($post->getTitle()));
            $em->persist($post);
        }

        $em->flush();

        $output->writeln('Done');
    }

}
