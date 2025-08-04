<?php

/**
 * Nylas Email Body Loader Service.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Service
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Service;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailManager;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\EmailBundle\Builder\EmailBodyBuilder;
use Oro\Bundle\EmailBundle\Builder\EmailEntityBuilder;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\EmailBody;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Exception\EmailBodyNotFoundException;
use Oro\Bundle\EmailBundle\Provider\EmailBodyLoaderInterface;

/**
 * Nylas Email Body Loader Service.
 *
 * Implements EmailBodyLoaderInterface to load email bodies from Nylas API.
 *
 * @package BrainStream\Bundle\NylasBundle\Service
 */
class NylasEmailBodyLoader implements EmailBodyLoaderInterface
{
    /** @var NylasClient */
    private NylasClient $nylasClient;

    /** @var ConfigManager */
    private ConfigManager $configManager;

    /** @var EmailEntityBuilder */
    private EmailEntityBuilder $emailEntityBuilder;

    /**
     * NylasEmailBodyLoader constructor.
     *
     * @param NylasClient        $nylasClient        The Nylas client service
     * @param ConfigManager      $configManager      The configuration manager
     * @param EmailEntityBuilder $entityBuilder      The email entity builder
     */
    public function __construct(
        NylasClient $nylasClient,
        ConfigManager $configManager,
        EmailEntityBuilder $entityBuilder
    ) {
        $this->nylasClient = $nylasClient;
        $this->configManager = $configManager;
        $this->emailEntityBuilder = $entityBuilder;
    }

    /**
     * Check if this loader supports the given email origin.
     *
     * @param EmailOrigin $origin The email origin to check
     *
     * @return bool
     */
    public function supports(EmailOrigin $origin): bool
    {
        return $origin instanceof NylasEmailOrigin;
    }

    /**
     * Load email body from Nylas API.
     *
     * @param EmailFolder           $folder The email folder
     * @param Email                 $email  The email entity
     * @param EntityManagerInterface $em     The entity manager
     *
     * @return EmailBody
     *
     * @throws EmailBodyNotFoundException When email body is not found
     */
    public function loadEmailBody(EmailFolder $folder, Email $email, EntityManagerInterface $em): EmailBody
    {
        /** @var NylasEmailOrigin $origin */
        $origin = $folder->getOrigin();

        $this->nylasClient->setEmailOrigin($origin);

        $manager = new NylasEmailManager($this->nylasClient, $this->emailEntityBuilder, $em);
        $manager->selectFolder($folder->getFullName());

        $repo = $em->getRepository('Oro\Bundle\EmailBundle\Entity\Email');
        $query = $repo->createQueryBuilder('e')
            ->select('e.uid')
            ->where('nef.uid = ?1 AND nef.folder = ?2')
            ->setParameter(1, $email->getUId())
            ->setParameter(2, $folder)
            ->getQuery();

        /** @var \BrainStream\Bundle\NylasBundle\Manager\DTO\Email $loadedEmail */
        $loadedEmail = $manager->findEmail($query->getSingleScalarResult());
        if (null === $loadedEmail) {
            throw new EmailBodyNotFoundException($email);
        }

        $builder = new EmailBodyBuilder($this->configManager);
        $builder->setEmailBody(
            $loadedEmail->getBody()->getContent(),
            $loadedEmail->getBody()->getBodyIsText()
        );
        foreach ($loadedEmail->getAttachments() as $attachment) {
            $builder->addEmailAttachment(
                $attachment->getFileName(),
                $attachment->getContent(),
                $attachment->getContentType(),
                $attachment->getContentTransferEncoding(),
                $attachment->getContentId(),
                $attachment->getFileSize()
            );
        }

        return $builder->getEmailBody();
    }
}
