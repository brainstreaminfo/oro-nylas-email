<?php

/**
 * Nylas Email Folder Entity.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Entity
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;

/**
 * Nylas Email Folder Entity.
 *
 * Extends the base email folder entity for Nylas integration.
 *
 * @package BrainStream\Bundle\NylasBundle\Entity
 */
#[ORM\Entity(repositoryClass: "BrainStream\Bundle\NylasBundle\Entity\Repository\EmailFolderRepository")]
#[ORM\Table(name: "oro_email_folder")]
class NylasEmailFolder extends EmailFolder
{

    #[ORM\Column(name: "folder_uid", type: "string", length: 255, nullable: true)]
    protected $folderUid;

    /**
     * Get folder UID.
     *
     * @return string|null The folder UID or null if not set
     */
    public function getFolderUid(): ?string
    {
        return $this->folderUid;
    }

    /**
     * Set folder UID.
     *
     * @param string|null $folderUid The folder UID to set
     *
     * @return void
     */
    public function setFolderUid(?string $folderUid): void
    {
        $this->folderUid = $folderUid;
    }
}
