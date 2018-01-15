<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PostUser
 *
 * @ORM\Table(name="post_user")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PostUserRepository")
 */
class PostUser
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\ManyToOne(targetEntity="Post", inversedBy="postUsers")
     */
    private $post;

    /**
     * @var string
     *
     * @ORM\ManyToOne(targetEntity="User", inversedBy="postUsers")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="ip_adress", type="string", length=255, nullable=true)
     */
    private $ipAdress;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="readedAt", type="datetimetz")
     */
    private $readedAt;

    /**
     * @var int
     *
     * @ORM\Column(name="nbReads", type="integer")
     */
    private $nbReads = 0;


    public function __toString() {
        return "" . $this->getId();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set post
     *
     * @param string $post
     *
     * @return PostUser
     */
    public function setPost($post)
    {
        $this->post = $post;

        return $this;
    }

    /**
     * Get post
     *
     * @return string
     */
    public function getPost()
    {
        return $this->post;
    }

    /**
     * Set user
     *
     * @param string $user
     *
     * @return PostUser
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set readedAt
     *
     * @param \DateTime $readedAt
     *
     * @return PostUser
     */
    public function setReadedAt($readedAt)
    {
        $this->readedAt = $readedAt;

        return $this;
    }

    /**
     * Get readedAt
     *
     * @return \DateTime
     */
    public function getReadedAt()
    {
        return $this->readedAt;
    }

    /**
     * Set nbReads
     *
     * @param integer $nbReads
     *
     * @return PostUser
     */
    public function setNbReads($nbReads)
    {
        $this->nbReads = $nbReads;

        return $this;
    }

    /**
     * Get nbReads
     *
     * @return int
     */
    public function getNbReads()
    {
        return $this->nbReads;
    }

    /**
     * Set ipAdress
     *
     * @param string $ipAdress
     *
     * @return PostUser
     */
    public function setIpAdress($ipAdress)
    {
        $this->ipAdress = $ipAdress;

        return $this;
    }

    /**
     * Get ipAdress
     *
     * @return string
     */
    public function getIpAdress()
    {
        return $this->ipAdress;
    }
}
