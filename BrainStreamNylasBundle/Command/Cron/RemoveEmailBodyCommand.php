<?php

/**
 * Remove Email Body Command.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Command\Cron
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

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

/**
 * Remove Email Body Command.
 *
 * Console command for purging email bodies and attachments.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Command\Cron
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class RemoveEmailBodyCommand extends ContainerAwareCommand implements CronCommandInterface
{
    public const NAME = 'oro:cron:email-body-purge';

    public const LAST_NUMBER_OF_DAYS = 'days';

    public const LAST_NUMBER_OF_DAYS_DEFAULT = 7;

    public const LIMIT = 100;

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure(): void
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
     * Execute the command.
     *
     * @param InputInterface  $input  The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption(static::LAST_NUMBER_OF_DAYS);

        $emailBodys = $this->getEmailBody($days);

        $count = count($emailBodys);
        if ($count) {
            $em = $this->getEntityManager();
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

        return self::SUCCESS;
    }

    /**
     * Get default cron schedule definition.
     *
     * @return string
     */
    public function getDefaultDefinition(): string
    {
        return '1 0 * * *';
    }

    /**
     * Remove email body, attachments and attachments content.
     *
     * @param EntityManager $em        The entity manager
     * @param EmailBody     $emailBody The email body
     *
     * @return void
     */
    protected function removeBodyAndAttachments(EntityManager $em, EmailBody $emailBody): void
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
     * Fetch email body.
     *
     * @param int $days The number of days
     *
     * @return BufferedQueryResultIterator
     */
    protected function getEmailBody(int $days): BufferedQueryResultIterator
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
     * Get entity manager.
     *
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('doctrine')->getEntityManager();
    }
}
