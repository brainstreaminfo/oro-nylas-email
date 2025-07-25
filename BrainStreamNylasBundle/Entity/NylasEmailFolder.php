<?php

namespace BrainStream\Bundle\NylasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;

#[ORM\Entity(repositoryClass: "BrainStream\Bundle\NylasBundle\Entity\Repository\EmailFolderRepository")]
#[ORM\Table(name: "oro_email_folder")]
class NylasEmailFolder extends EmailFolder
{

    #[ORM\Column(name: "folder_uid", type: "string", length: 255, nullable: true)]
    protected $folderUid;

    /**
     * Get folder UID
     *
     * @return string|null
     */
    public function getFolderUid(): ?string
    {
        return $this->folderUid;
    }

    /**
     * Set folder UID
     *
     * @param string|null $folderUid
     */
    public function setFolderUid(?string $folderUid): void
    {
        $this->folderUid = $folderUid;
    }
}
