<?php

namespace BrainStream\Bundle\NylasBundle\Command\Cron;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\BatchBundle\ORM\Query\BufferedQueryResultIterator;
use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use Oro\Bundle\EmailBundle\Entity\EmailBody;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveEmailBodyCommand extends ContainerAwareCommand implements CronCommandInterface
{
    const NAME                        = 'oro:cron:email-body-purge';
    const LAST_NUMBER_OF_DAYS         = 'days';
    const LAST_NUMBER_OF_DAYS_DEFAULT = 7;
    const LIMIT                       = 100;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(static::NAME)
            ->setDescription('Purges emails body & attachments')
            ->addOption(
                static::LAST_NUMBER_OF_DAYS,
                null,
                InputOption::VALUE_OPTIONAL,
                'Purges email body & attachments that option days are old',
                static::LAST_NUMBER_OF_DAYS_DEFAULT
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $days = $input->getOption(static::LAST_NUMBER_OF_DAYS);

        $emailBodys = $this->getEmailBody($days);

        $count = count($emailBodys);
        if ($count) {
            $em       = $this->getEntityManager();
            $progress = new ProgressBar($output, $count);
            $progress->setFormat('debug');

            $progress->start();
            foreach ($emailBodys as $emailBody) {
                $this->removeBodyAndAttachments($em, $emailBody);
                $progress->advance();
            }
            $progress->finish();
        } else {
            $output->writeln('No emails body to purify.');
        }
    }

    /**
     * @return string
     */
    public function getDefaultDefinition()
    {
        return '1 0 * * *';
    }

    /**
     * Remove email body, attachments and attachments content
     *
     * @param EntityManager $em
     * @param EmailBody     $emailBody
     */
    protected function removeBodyAndAttachments(EntityManager $em, EmailBody $emailBody)
    {
        foreach ($emailBody->getAttachments() as $attachment) {
            if ($attachment->getContent() !== null) {
                //Remove email attachments content
                $em->remove($attachment->getContent());
            }
            //Remove email attachments
            $em->remove($attachment);
        }
        //Remove email body
        $em->remove($emailBody);
    }

    /**
     * Fetch email body
     *
     * @param $days
     *
     * @return BufferedQueryResultIterator
     */
    protected function getEmailBody($days)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQuery("SELECT eb FROM OroEmailBundle:EmailBody eb WHERE eb.created < :oldDate")
                 ->setParameter('oldDate', date('Y-m-d H:i:s', strtotime('-' . $days . ' Days')));

        $emailBody = (new BufferedQueryResultIterator($qb))
            ->setBufferSize(static::LIMIT)
            ->setPageCallback(
                function () use ($em) {
                    $em->flush();
                    $em->clear();
                }
            );

        return $emailBody;
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getContainer()->get('doctrine')->getEntityManager();
    }
}
