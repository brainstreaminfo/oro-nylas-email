<?php


namespace BrainStream\Bundle\NylasBundle\Service;


use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailManager;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\EmailBundle\Builder\EmailBodyBuilder;
use Oro\Bundle\EmailBundle\Builder\EmailEntityBuilder;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\EmailBody;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Exception\EmailBodyNotFoundException;
use Oro\Bundle\EmailBundle\Provider\EmailBodyLoaderInterface;
use Doctrine\ORM\EntityManagerInterface;


class NylasEmailBodyLoader implements EmailBodyLoaderInterface
{
    /** @var NylasClient */
    private $nylasClient;

    /** @var ConfigManager */
    private $configManager;

    /** @var EmailEntityBuilder */
    private $emailEntityBuilder;

    /**
     * NylasEmailBodyLoader constructor.
     */
    public function __construct(NylasClient $nylasClient, ConfigManager $configManager, EmailEntityBuilder $entityBuilder)
    {
        $this->nylasClient = $nylasClient;
        $this->configManager = $configManager;
        $this->emailEntityBuilder = $entityBuilder;
    }
    /**
     * @param EmailOrigin $origin
     * @return bool
     */
    public function supports(EmailOrigin $origin): bool
    {
        return $origin instanceof NylasEmailOrigin;
    }

    /**
     * @param EmailFolder $folder
     * @param Email $email
     * @param EntityManager $em
     * @return EmailBody|void
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
