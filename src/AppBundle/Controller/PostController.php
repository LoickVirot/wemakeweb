<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Category;
use AppBundle\Entity\Comment;
use AppBundle\Entity\Post;
use AppBundle\Entity\PostUser;
use AppBundle\Service\Parsedown;
use AppBundle\Form\CommentType;
use AppBundle\Tests\Controller\CategoryControllerTest;
use AppBundle\Service\CurlSender;
use AppBundle\Utils\UrlUtils;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

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
            $posts = $em->getRepository('AppBundle:Post')->findBy(['published' => true], ['creationDate'=> 'desc']);
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
            'category' => $category,
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
            // Check if content is empty (cannot do this into form, because of markdown editor)
            if (empty($post->getContent())) {
                $this->addFlash('danger', $this->get('translator')->trans("page.post.new.error.no-content"));
                return $this->redirectToRoute("post_new");
            }

            // Stop XSS insertion to content
            $post->setContent(str_ireplace('<script>', '&lt;script&gt;', $post->getContent()));
            $post->setContent(str_ireplace('</script>', '&lt;/script&gt;', $post->getContent()));

            $post->setSlug(uniqid() . '-' . UrlUtils::slugify($post->getTitle()));

            $post->setAuthor($this->getUser());

            if ($post->getPublished()) {
                $post->setPublicationDate(new \DateTime());
            }

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
            'isNew' => true
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
        // Is post published or draft
        if (!$post->getPublished() && $this->getUser() !== $post->getAuthor()) {
            throw $this->createNotFoundException('Access denied : This post is not published');
        }

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

        $em->persist($postUser);
        $em->flush();

        //manage tags
        $tags = $post->getTags();
        $tags = explode(',', $tags);
        $tags = implode(', ', $tags);
        $post->setTags($tags);

        $nbViews = $em->getRepository('AppBundle:PostUser')->getNbReads($post);

        $deleteForm = $this->createDeleteForm($post);

        // Parsedown to html
        $post->setContent($this->getParsedText($post->getContent()));

        return $this->render('post/show.html.twig', array(
            'post' => $post,
            'nbViews' => $nbViews,
            'delete_form' => $deleteForm->createView(),
            'comment_form' => $this->createCommentForm(new Comment(), $post)->createView()
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
            $this->addFlash('danger', 'Permission denied');
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
            // Check if content is empty (cannot do this into form, because of markdown editor)
            if (empty($post->getContent())) {
                $this->addFlash('danger', $this->get('translator')->trans("page.post.new.error.no-content"));
                return $this->redirectToRoute("post_new");
            }

            // Stop XSS insertion to content
            $data = $editForm->getData();
            $data->setContent(str_ireplace('<script>', '&lt;script&gt;', $data->getContent()));
            $data->setContent(str_ireplace('</script>', '&lt;/script&gt;', $data->getContent()));

            if ($data->getPublished()) {
                if (is_null($data->getPublicationDate())) {
                    $data->setPublicationDate(new \DateTime());
                }
                else {
                    // Change last update
                    $data->setLastUpdate(new \DateTime());
                }
            }

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('post_show', array('slug' => $post->getSlug()));
        }

        return $this->render('post/edit.html.twig', array(
            'post' => $post,
            'form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
            'isNew' => false
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
            $this->addFlash("success", $this->get("translator")->trans("page.post.delete.flash.confirmation"));
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

    /**
     * Create a comment (AJAX required)
     * @Route("/post/{id}/comment/{comment}", name="post_comment", condition="request.isXmlHttpRequest()", defaults={"comment" = null})
     * @Method("POST")
     * @ParamConverter("id", options={"mapping": {"id": "post"}})
     * @ParamConverter("comment", options={"mapping": {"comment": "answerTo"}})
     *
     * @param Request $request
     * @param Post $post
     * @param Comment|null $answerTo
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function commentPost(Request $request, Post $post, Comment $answerTo = null)
    {
        // Is user logged in
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $errorMessage = [
                "message" => "Access denied. You need to authenticate yourself."
            ];
            return $this->json(json_encode($errorMessage), 401);
        }

        $comment = new Comment();
        $form = $this->createForm('AppBundle\Form\CommentType', $comment);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $comment->setAuthor($this->getUser());
            $comment->setPost($post);
            if (!is_null($answerTo)) {
                $comment->addComment($answerTo);
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($comment);
            $em->flush();


            $message = [
                "message" => "OK"
            ];
            return $this->json(json_encode($message));
        }

        $errorMessage = [
            "message" => "invalid data"
        ];
        return $this->json(json_encode($errorMessage), 400);
    }

    /**
     * Creates a form to create a Comment entity.
     */
    private function createCommentForm(Comment $comment, Post $post)
    {
        $form = $this->createForm(CommentType::class, $comment,
            array(
                'action' => $this->generateUrl('post_comment', ["id" => $post->getId()]),
                'method' => 'POST',
            )
        );

        return $form;
    }


    /**
     * Create a comment (AJAX required)
     * @Route("/post/{id}/comment/", name="post_get_comment")
     * @Method("GET")
     * @ParamConverter("id", options={"mapping": {"id": "post"}})
     *
     * @param Post $post
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getCommentsPosts(Post $post)
    {
        $result = array();
        /** @var Comment $comment */
        foreach ($post->getComments()->toArray() as $comment) {
            $result[] = [
                "id" => $comment->getId(),
                "author" => [
                    "username" => htmlentities($comment->getAuthor()->getUsername()),
                    "profileUrl" => $this->generateUrl("user_show", ["username" => $comment->getAuthor()->getUsername()]),
                    "profilePicture" => $comment->getAuthor()->getProfilePicture()
                ],
                "comment" => [
                    "content" => htmlentities($comment->getContent()),
                    "answerTo" => $comment->getAnswerTo(),
                    "date" => $comment->getDate()->format('d/m/Y')
                ]
            ];
        }
        return $this->json($result);
    }

    /**
     * Create a comment (AJAX required)
     * @Route("/post/comment/{comment}", name="post_delete_comment")
     * @Method("DELETE")
     * @Security("has_role('IS_AUTHENTICATED_FULLY')")
     *
     * @param Comment $comment
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteCommentPost(Comment $comment)
    {
        if ($comment->getAuthor() !== ($this->getUser())) {
            return $this->json(json_encode("Not authorized" ), 401);
        }

        try {
            $em = $this->getDoctrine()->getManager();
            $em->remove($comment);
            $em->flush();
            return $this->json(json_encode("deleted"));
        } catch(\Exception $e) {
            return $this->json(json_encode("error : " . $e->getMessage() ), 400);
        }
    }

    /**
     * Take a markdown text and return the HTML
     * @Route("/post/preview", name="post_preview")
     * @Method("POST")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getPreviewPost(Request $request) {
        $text = $request->request->get('text');

        if (is_null($text)) {
            return $this->json("Text value cannot be null", 400);
        }

        return $this->json(["result" => $this->getParsedText($text)]);
    }

    private function getParsedText(String $text)
    {
        $parser = new Parsedown();
        return $parser->parse($text);
    }
}
