<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FileRepository")
 */
class File
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
    private $filetype;

    /**
     * @ORM\Column(type="text")
     */
    private $filename;

    /**
     * @ORM\Column(type="text")
     */
    private $org_filename;

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

    public function getFiletype(): ?string
    {
        return $this->filetype;
    }

    public function setFiletype(string $filetype): self
    {
        $this->filetype = $filetype;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getOrgFilename(): ?string
    {
        return $this->org_filename;
    }

    public function setOrgFilename(string $org_filename): self
    {
        $this->org_filename = $org_filename;

        return $this;
    }
}
