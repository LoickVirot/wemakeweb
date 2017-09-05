<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DefaultController extends Controller
{
    public function staticPageAction(Request $request, $path)
    {
        $em = $this->getDoctrine()->getManager();

        //Homepage is called
        if (empty($path)) {
            $path = '/';
        }

        $staticPage = $em->getRepository('AppBundle:StaticPage')->findOneBy([
            'url' => $path
            ]
        );

        if (!is_null($staticPage)) {
            return $this->render('base.html.twig', ['staticcontent' => $staticPage->getContent()]);
        }

        throw new NotFoundHttpException('Route not found');
    }
}
