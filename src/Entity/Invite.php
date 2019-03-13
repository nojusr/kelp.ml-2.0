<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InviteRepository")
 */
class Invite
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $invite_key;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInviteKey(): ?string
    {
        return $this->invite_key;
    }

    public function setInviteKey(?string $invite_key): self
    {
        $this->invite_key = $invite_key;

        return $this;
    }
}
