<?php
// src/AppBundle/Entity/User.php

namespace AppBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * @ORM\Entity
 * @ORM\Table(name="fos_user")
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
    * @ORM\OneToMany(targetEntity="Post", mappedBy="author" )
    */
    protected $posts;

    /**
     * @ORM\Column(name="profile_picture", type="text", length=255, nullable=true)
     *
     * @Assert\File(mimeTypes={ "image/png", "image/jpeg", "image/jpg" })
     */
    protected $profilePicture;

    /**
    * @ORM\Column(name="firstname", type="string", length=255, nullable=true)
    */
    protected $firstname;

    /**
    * @ORM\Column(name="lastname", type="string", length=255, nullable=true)
    */
    protected $lastname;

    /**
    * @ORM\Column(name="twitter", type="string", length=255, nullable=true)
    */
    protected $twitter;

    /**
    * @ORM\Column(name="website", type="string", length=255, nullable=true)
    */
    protected $website;

    /**
     * @ORM\OneToMany(targetEntity="PostUser", mappedBy="user")
     */
    private $postUsers;

    public function __construct()
    {
        parent::__construct();
        $this->setProfilePicture(null);
    }

    /**
     * Add post
     *
     * @param \AppBundle\Entity\Post $post
     *
     * @return User
     */
    public function addPost(\AppBundle\Entity\Post $post)
    {
        $this->posts[] = $post;

        return $this;
    }

    /**
     * Remove post
     *
     * @param \AppBundle\Entity\Post $post
     */
    public function removePost(\AppBundle\Entity\Post $post)
    {
        $this->posts->removeElement($post);
    }

    /**
     * Get posts
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPosts()
    {
        return $this->posts;
    }

    /**
     * Set firstname
     *
     * @param string $firstname
     *
     * @return User
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Get firstname
     *
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Set lastname
     *
     * @param string $lastname
     *
     * @return User
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get lastname
     *
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Set twitter
     *
     * @param string $twitter
     *
     * @return User
     */
    public function setTwitter($twitter)
    {
        $this->twitter = $twitter;

        return $this;
    }

    /**
     * Get twitter
     *
     * @return string
     */
    public function getTwitter()
    {
        return $this->twitter;
    }

    /**
     * Set website
     *
     * @param string $website
     *
     * @return User
     */
    public function setWebsite($website)
    {
        $this->website = $website;

        return $this;
    }

    /**
     * Get website
     *
     * @return string
     */
    public function getWebsite()
    {
        return $this->website;
    }

    /**
     * Set profilePicture
     *
     * @param string $profilePicture
     *
     * @return User
     */
    public function setProfilePicture($profilePicture)
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    /**
     * Get profilePicture
     *
     * @return string
     */
    public function getProfilePicture()
    {
        return $this->profilePicture;
    }

    /**
     * Add postUser
     *
     * @param \AppBundle\Entity\PostUser $postUser
     *
     * @return User
     */
    public function addPostUser(\AppBundle\Entity\PostUser $postUser)
    {
        $this->postUsers[] = $postUser;

        return $this;
    }

    /**
     * Remove postUser
     *
     * @param \AppBundle\Entity\PostUser $postUser
     */
    public function removePostUser(\AppBundle\Entity\PostUser $postUser)
    {
        $this->postUsers->removeElement($postUser);
    }

    /**
     * Get postUsers
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPostUsers()
    {
        return $this->postUsers;
    }
}
