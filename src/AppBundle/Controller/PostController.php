<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Category;
use AppBundle\Entity\Post;
use AppBundle\Entity\PostUser;
use AppBundle\Service\Parsedown;
use AppBundle\Tests\Controller\CategoryControllerTest;
use AppBundle\Service\CurlSender;
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
            $posts = $em->getRepository('AppBundle:Post')->findBy([], ['creationDate'=> 'desc']);
        } else {
            $posts = $em->getRepository('AppBundle:Post')->findBy([
                "category" => $category
            ]);
        }

        // Class posts to fill columns and parse markdown
        $parsedown = new Parsedown();
        $column = 0;
        $sortedPosts = array();
        foreach ($posts as $post) {
            // Parse markdown and remove html tags
            $post->setContent(strip_tags($parsedown->parse($post->getContent())));

            $sortedPosts[$column][$post->getId()]['entity'] = $post;
            $sortedPosts[$column][$post->getId()]['nbViews'] = $em
                ->getRepository('AppBundle:PostUser')
                ->getNbReads($post);
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

            // Send article to Discord
            $headers = [
                "content" => $this->get('translator')->trans("page.post.webhook"),
                "embeds" => [
                    [
                        "author" => [
                            "name"  => $post->getAuthor()->getUsername(),
                            "url"   => $this->getParameter("prod_url"). $this->generateUrl("user_show", ["username" => $post->getAuthor()->getUsername()]),
                            "icon_url" => $this->getParameter("prod_url") . '/' . $this->getParameter("profile_picture_directory_twig") . $post->getAuthor()->getProfilePicture()
                        ],
                        "title" => $post->getTitle(),
                        "description" => substr($post->getContent(), 0, 140) . "...",
                        "url" => $this->getParameter("prod_url") . $this->generateUrl("post_show", ["slug" => $post->getSlug()]),
                        "color" => 4172799
                    ]
                ]
            ];
            try {
                $curl = new CurlSender($this->getParameter("webhook_url"), CURLOPT_POST, $headers);
                $curl->send();
            } catch(\Exception $e) {
                // Not important, no excetion need to be throwed
            }

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
    public function showAction(Request $request, Post $post)
    {
        // Get or create association between post and actual user
        $em = $this->getDoctrine()->getManager();
        $params = [
            'post' => $post,
        ];
        if (is_null($this->getUser())) {
            $params['ipAdress'] = $request->getClientIp();
        } else {
            $params['user'] = $this->getUser();
        }
        $postUser = $em->getRepository('AppBundle:PostUser')->findOneBy($params);

        if ($postUser == null) {
            $postUser = new PostUser();
            $postUser->setPost($post);
            if (is_null($this->getUser())) {
                $postUser->setIpAdress($request->getClientIp());
            } else {
                $postUser->setUser($this->getUser());
            }
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

        //manage tags
        $tags = $post->getTags();
        $tags = explode(',', $tags);
        $tags = implode(', ', $tags);
        $post->setTags($tags);


        $em->persist($postUser);
        $em->flush();

        $nbViews = $em->getRepository('AppBundle:PostUser')->getNbReads($post);

        $deleteForm = $this->createDeleteForm($post);

        // Parsedown to html
        $parser = new Parsedown();
        $post->setContent($parser->parse($post->getContent()));

        return $this->render('post/show.html.twig', array(
            'post' => $post,
            'nbViews' => $nbViews,
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
