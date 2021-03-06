<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Service\Parsedown;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * User controller.
 */
class UserController extends Controller
{
    /**
     * Finds and displays a user entity.
     *
     * @Route("/u/{username}", name="user_show")
     * @ParamConverter("username", options={"mapping": {"username": "user"}})
     * @Method("GET")
     */
    public function showAction(User $user)
    {
        $parsedown = new Parsedown();
        $em = $this->getDoctrine()->getManager();
        $posts = [
            'published' => [],
            'draft' => []
        ];
        foreach ($user->getPosts() as $post) {
            // Is the user's posts, get not published posts and add it to an array
            if ($user === $this->getUser()) {
                if (!$post->getPublished()) {
                    $post->setContent(strip_tags($parsedown->parse($post->getContent())));
                    $posts['draft'][$post->getId()]['entity'] = $post;
                    $posts['draft'][$post->getId()]['nbviews'] = $em->getRepository('AppBundle:PostUser')->getNbReads($post);
                }
            }
            if ($post->getPublished()) {
                $post->setContent(strip_tags($parsedown->parse($post->getContent())));
                $posts['published'][$post->getId()]['entity'] = $post;
                $posts['published'][$post->getId()]['nbviews'] = $em->getRepository('AppBundle:PostUser')->getNbReads($post);
            }
        }

        $deleteForm = $this->createDeleteForm($user);

        return $this->render('user/show.html.twig', array(
            'user' => $user,
            'posts' => $posts,
            'delete_form' => $deleteForm->createView()
        ));
    }

    /**
     * Displays a form to edit an existing user entity.
     *
     * @Route("/u/{id}/edit", name="user_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, User $user)
    {
        $oldProfilePicture = $user->getProfilePicture();

        if ($this->getUser() != $user) {
            $session = new Session();
            $session->getFlashBag()->add('danger', 'access denied');
            return $this->redirectToRoute('user_show', ['username' => $user->getUsername()]);
        }
        $deleteForm = $this->createDeleteForm($user);
        $editForm = $this->createForm('AppBundle\Form\UserType', $user);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $picture = $user->getProfilePicture();

            if (!is_null($picture)) {
                $pictureName = md5(uniqid()).'.'.$picture->guessExtension();

                $picture->move(
                    $this->getParameter('profile_picture_directory'),
                    $pictureName
                );

                if (!is_null($oldProfilePicture)) {
                    $oldFilePath = $this->getParameter('profile_picture_directory') . '/' . $oldProfilePicture;
                    if(file_exists($oldFilePath)) unlink($oldFilePath);
                }

                $user->setProfilePicture($pictureName);
            } else {
                $user->setProfilePicture($oldProfilePicture);
            }

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('user_edit', array('id' => $user->getId()));
        }

        return $this->render('user/edit.html.twig', array(
            'user' => $user,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a user entity.
     *
     * @Route("/u/{id}", name="user_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, User $user)
    {
        $form = $this->createDeleteForm($user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();

            $session = new Session();
            $session->getFlashBag()->add('success', $this->get('translator')->trans("page.user.edit.delete.success"));
        }

        return $this->redirect("/");
    }

    /**
     * Creates a form to delete a user entity.
     *
     * @param User $user The user entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(User $user)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('user_delete', array('id' => $user->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
