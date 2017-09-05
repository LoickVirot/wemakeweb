<?php
/**
 * Created by PhpStorm.
 * User: Loïck
 * Date: 05/09/2017
 * Time: 22:05
 */

namespace AppBundle\EventListener;


use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RouteNotFoundListener
{
    private $router;

    private $entityManager;

    /**
     * RouteNotFoundListener constructor.
     * @param $router
     * @param $entityManager
     */
    public function __construct(Router $router, EntityManager $entityManager)
    {
        $this->router = $router;
        $this->entityManager = $entityManager;
    }

    public function onKernelException(GetResponseForExceptionEvent $event) {
        $exception = $event->getException();
        dump("oui");die;

        if ($exception instanceof NotFoundHttpException) {
            dump("oui");die;
        }
    }

}