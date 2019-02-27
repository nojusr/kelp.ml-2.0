<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PasteRepository")
 */
class Paste
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $corr_uid;

    /**
     * @ORM\Column(type="text")
     */
    private $paste_name;

    /**
     * @ORM\Column(type="text")
     */
    private $paste_text;

    /**
     * @ORM\Column(type="text")
     */
    private $real_id;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCorrUid(): ?int
    {
        return $this->corr_uid;
    }

    public function setCorrUid(int $corr_uid): self
    {
        $this->corr_uid = $corr_uid;

        return $this;
    }

    public function getPasteName(): ?string
    {
        return $this->paste_name;
    }

    public function setPasteName(string $paste_name): self
    {
        $this->paste_name = $paste_name;

        return $this;
    }

    public function getPasteText(): ?string
    {
        return $this->paste_text;
    }

    public function setPasteText(string $paste_text): self
    {
        $this->paste_text = $paste_text;

        return $this;
    }

    public function getRealId(): ?string
    {
        return $this->real_id;
    }

    public function setRealId(string $real_id): self
    {
        $this->real_id = $real_id;

        return $this;
    }
}
