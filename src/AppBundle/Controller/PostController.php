<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Category;
use AppBundle\Entity\Post;
use AppBundle\Entity\PostUser;
use AppBundle\Tests\Controller\CategoryControllerTest;
use AppBundle\Utils\UrlUtils;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Post controller.
 *
 */
class PostController extends Controller
{
    /**
     * Lists all post entities.
     *
     * @Route("/post", name="post_index")
     * @Method("GET")
     */
    public function indexAction(Category $category = null)
    {
        $em = $this->getDoctrine()->getManager();

        if (is_null($category)) {
            $posts = $em->getRepository('AppBundle:Post')->findAll();
        } else {
            $posts = $em->getRepository('AppBundle:Post')->findBy([
                "category" => $category
            ]);
        }

        $column = 0;
        $sortedPosts = array();
        foreach ($posts as $post) {
            $sortedPosts[$column][] = $post;
            if ($column == 2) {
                $column = 0;
            } else {
                $column ++;
            }
        }

        return $this->render('post/index.html.twig', array(
            'posts' => $sortedPosts,
            'category' => $category
        ));
    }

    /**
     * Creates a new post entity.
     *
     * @Route("/post/new", name="post_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function newAction(Request $request)
    {
        $post = new Post();
        $form = $this->createForm('AppBundle\Form\PostType', $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Stop XSS insertion to content
            $post->setContent(str_ireplace('<script>', '&lt;script&gt;', $post->getContent()));
            $post->setContent(str_ireplace('</script>', '&lt;/script&gt;', $post->getContent()));

            $post->setSlug(uniqid() . '-' . UrlUtils::slugify($post->getTitle()));

            $post->setAuthor($this->getUser());

            $em = $this->getDoctrine()->getManager();
            $em->persist($post);
            $em->flush();

            return $this->redirectToRoute('post_show', array('slug' => $post->getSlug()));
        }

        return $this->render('post/new.html.twig', array(
            'post' => $post,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a post entity.
     *
     * @Route("/post/{slug}", name="post_show")
     * @ParamConverter("slug", options={"mapping": {"slug": "post"}})
     * @Method("GET")
     */
    public function showAction(Post $post)
    {
        // Get or create association between post and actual user
        $em = $this->getDoctrine()->getManager();
        $postUser = $em->getRepository('AppBundle:PostUser')->findOneBy([
            'post' => $post,
            'user' => $this->getUser()
        ]);

        if ($postUser == null) {
            $postUser = new PostUser();
            $postUser->setPost($post);
            $postUser->setUser($this->getUser());
            $postUser->setReadedAt(new \DateTime());
            $postUser->setNbReads($postUser->getNbReads() + 1);
        } else {
            // Check if last read is before 30 minutes
            $time = new \DateTime();
            $time->modify('-15 minutes');
            if ($postUser->getReadedAt()->getTimestamp() < $time->getTimestamp()) {
                $postUser->setReadedAt(new \DateTime());
                $postUser->setNbReads($postUser->getNbReads() + 1);
            }
        }

        $em->persist($postUser);
        $em->flush();

        $deleteForm = $this->createDeleteForm($post);

        return $this->render('post/show.html.twig', array(
            'post' => $post,
            'postUser' => $postUser,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing post entity.
     *
     * @Route("/post/{id}/edit", name="post_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_USER')")     
     */
    public function editAction(Request $request, Post $post)
    {
        if (!$this->getUser()->hasRole('ROLE_ADMIN') and ($this->getUser() != $post->getAuthor())) {
            $session = new Session();
            $session->getFlashBag()->add('danger', 'Permission denied');
            $redirect = $request->headers->get('referer');
            if (is_null($redirect)) {
                $redirect = $this->generateUrl('post_index');
            }
            return $this->redirect($redirect);
        } 

        $deleteForm = $this->createDeleteForm($post);
        $editForm = $this->createForm('AppBundle\Form\PostType', $post);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            // Stop XSS insertion to content
            $data = $editForm->getData();
            $data->setContent(str_ireplace('<script>', '&lt;script&gt;', $data->getContent()));
            $data->setContent(str_ireplace('</script>', '&lt;/script&gt;', $data->getContent()));

            // Change last update
            $data->setLastUpdate(new \DateTime());

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('post_edit', array('id' => $post->getId()));
        }

        return $this->render('post/edit.html.twig', array(
            'post' => $post,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a post entity.
     *
     * @Route("/post/{id}", name="post_delete")
     * @Method("DELETE")
     * @Security("has_role('ROLE_USER')")     
     */
    public function deleteAction(Request $request, Post $post)
    {
        if (!$this->getUser()->hasRole('ROLE_ADMIN') and ($this->getUser() != $post->getAuthor())) {
            $session = new Session();
            $session->getFlashBag()->add('danger', 'Permission denied');
            $redirect = $request->headers->get('referer');
            if (is_null($redirect)) {
                $redirect = $this->generateUrl('post_index');
            }
            return $this->redirect($redirect);
        } 

        $form = $this->createDeleteForm($post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($post);
            $em->flush();
        }

        return $this->redirectToRoute('post_index');
    }

    /**
     * Creates a form to delete a post entity.
     *
     * @param Post $post The post entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Post $post)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('post_delete', array('id' => $post->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }


    /**
     * @Route("/category/{slug}", name="post_category")
     * @Method("GET")
     * @ParamConverter("slug", options={"mapping": {"slug": "category"}})
     *
     * @param Category $category
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postByCategoryAction(Category $category)
    {
        return $this->indexAction($category);
    }
}
