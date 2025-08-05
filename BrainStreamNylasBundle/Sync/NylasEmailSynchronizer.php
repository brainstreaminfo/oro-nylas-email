<?php

/**
 * Nylas Email Synchronizer.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Sync
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Sync;

use BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailManager;
use BrainStream\Bundle\NylasBundle\Manager\NylasEmailRemoveManager;
use BrainStream\Bundle\NylasBundle\Service\NylasClient;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ActivityBundle\Manager\ActivityManager;
use Oro\Bundle\EmailBundle\Builder\EmailEntityBuilder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Exception\SyncFolderTimeoutException;
use Oro\Bundle\EmailBundle\Provider\EmailActivityListProvider;
use Oro\Bundle\EmailBundle\Sync\AbstractEmailSynchronizer;
use Oro\Bundle\EmailBundle\Sync\EmailSyncNotificationBag;
use Oro\Bundle\EmailBundle\Sync\KnownEmailAddressCheckerFactory;
use Oro\Bundle\EmailBundle\Sync\Model\SynchronizationProcessorSettings;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Bundle\NotificationBundle\NotificationAlert\NotificationAlertManager;
use Psr\Log\LoggerAwareInterface;

/**
 * Nylas Email Synchronizer.
 *
 * Handles synchronization of Nylas emails.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Sync
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class NylasEmailSynchronizer extends AbstractEmailSynchronizer
{
    public const SYNC_CODE_EXCEPTION = 4;

    private NylasClient $nylasClient;

    private EmailEntityBuilder $emailEntityBuilder;

    private NylasEmailRemoveManager $removeManager;

    private NylasEmailManager $nyalsEmailManager;

    private string $emailSyncInterval;

    private EmailActivityListProvider $emailActivityProvider;

    private ActivityManager $activityManager;

    private LocaleSettings $localeSettings;

    private string $clientIdentifier;

    private string $managerUrl;

    private NotificationAlertManager $notificationAlertManager;

    /**
     * Constructor for NylasEmailSynchronizer.
     *
     * @param ManagerRegistry                    $managerRegistry              The manager registry
     * @param KnownEmailAddressCheckerFactory   $knownEmailAddressCheckerFactory The known email address checker factory
     * @param NotificationAlertManager          $notificationAlertManager     The notification alert manager
     * @param NylasClient                       $nylasClient                  The Nylas client
     * @param EmailEntityBuilder                $emailEntityBuilder           The email entity builder
     * @param NylasEmailRemoveManager           $removeManager                The remove manager
     * @param NylasEmailManager                 $nylasEmailManager            The Nylas email manager
     * @param string                            $emailSyncInterval            The email sync interval
     * @param EmailActivityListProvider         $activityListProvider         The activity list provider
     * @param ActivityManager                   $activityManager              The activity manager
     * @param LocaleSettings                    $localeSettings               The locale settings
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        KnownEmailAddressCheckerFactory $knownEmailAddressCheckerFactory,
        NotificationAlertManager $notificationAlertManager,
        NylasClient $nylasClient,
        EmailEntityBuilder $emailEntityBuilder,
        NylasEmailRemoveManager $removeManager,
        NylasEmailManager $nylasEmailManager,
        string $emailSyncInterval,
        EmailActivityListProvider $activityListProvider,
        ActivityManager $activityManager,
        LocaleSettings $localeSettings
    ) {
        parent::__construct($managerRegistry, $knownEmailAddressCheckerFactory, $notificationAlertManager);
        $this->nylasClient = $nylasClient;
        $this->emailEntityBuilder = $emailEntityBuilder;
        $this->removeManager = $removeManager;
        $this->nyalsEmailManager = $nylasEmailManager;
        $this->emailSyncInterval = $emailSyncInterval;
        $this->emailActivityProvider = $activityListProvider;
        $this->activityManager = $activityManager;
        $this->localeSettings = $localeSettings;
        $this->clientIdentifier = '';
        $this->managerUrl = '';
    }

    /**
     * Check if synchronizer supports the given origin.
     *
     * @param EmailOrigin $origin The email origin
     *
     * @return bool
     */
    public function supports(EmailOrigin $origin): bool
    {
        return $origin instanceof NylasEmailOrigin;
    }

    /**
     * Get email origin class.
     *
     * @return string
     */
    protected function getEmailOriginClass(): string
    {
        return NylasEmailOrigin::class;
    }

    /**
     * Create synchronization processor.
     *
     * @param object $origin The email origin
     *
     * @return \Oro\Bundle\EmailBundle\Sync\AbstractEmailSynchronizationProcessor
     */
    protected function createSynchronizationProcessor($origin): \Oro\Bundle\EmailBundle\Sync\AbstractEmailSynchronizationProcessor
    {
        return new NylasEmailSynchronizationProcessor(
            $this->getEntityManager(),
            $this->emailEntityBuilder,
            $this->getKnownEmailAddressChecker(),
            $this->removeManager,
            $this->nylasClient,
            $this->nyalsEmailManager,
            $this->emailSyncInterval,
            $this->emailActivityProvider,
            $this->activityManager
        );
    }

    /**
     * Find origin to sync.
     *
     * @param int $maxConcurrentTasks   The maximum number of synchronization jobs running in the same time
     * @param int $minExecIntervalInMin The minimum time interval (in minutes) between two synchronizations of the same email origin
     *
     * @return EmailOrigin|null
     * @throws \Exception
     */
    protected function findOriginToSync($maxConcurrentTasks, $minExecIntervalInMin): ?EmailOrigin
    {
        $this->logger->info('Finding an email origin custom...');

        $now = $this->getCurrentUtcDateTime();
        $border = clone $now;
        if ($minExecIntervalInMin > 0) {
            $border->sub(new \DateInterval('PT' . $minExecIntervalInMin . 'M'));
        }
        $min = clone $now;
        $min->sub(new \DateInterval('P1Y'));

        // rules:
        // - items with earlier sync code modification dates have higher priority
        // - previously failed items are shifted at 30 minutes back (it means that if sync failed
        //   the next sync is performed only after 30 minutes)
        // - "In Process" items are moved at the end
        $repo = $this->getEntityManager()->getRepository($this->getEmailOriginClass());
        $query = $repo->createQueryBuilder('o')
            ->select(
                'o'
                . ', CASE WHEN o.syncCode = :inProcess THEN 0 ELSE 1 END AS HIDDEN p1'
                . ', (COALESCE(o.syncCode, 1000) * 30'
                . ' + TIMESTAMPDIFF(MINUTE, COALESCE(o.syncCodeUpdatedAt, :min), :now)'
                . ' / (CASE o.syncCode WHEN :success THEN 100 ELSE 1 END)) AS HIDDEN p2'
            )
            ->where('o.isActive = :isActive AND (o.syncCodeUpdatedAt IS NULL OR o.syncCodeUpdatedAt <= :border) AND o.syncCode IN (:syncCode)')
            ->orderBy('p1, p2 DESC, o.syncCodeUpdatedAt')
            ->setParameter('inProcess', self::SYNC_CODE_IN_PROCESS)
            ->setParameter('success', self::SYNC_CODE_SUCCESS)
            ->setParameter('isActive', true)
            ->setParameter('now', $now)
            ->setParameter('min', $min)
            ->setParameter('border', $border)
            ->setParameter('syncCode', [2, 3])
            ->setMaxResults($maxConcurrentTasks + 1)
            ->getQuery();

        $origins = $query->getResult();
        $result = null;
        foreach ($origins as $origin) {
            if ($origin->getSyncCode() !== self::SYNC_CODE_IN_PROCESS) {
                $result = $origin;
                break;
            }
        }

        if ($result === null) {
            if (!empty($origins)) {
                $this->logger->info('The maximum number of concurrent tasks is reached.');
            }
            $this->logger->info('An email origin was not found.');
        } else {
            $this->logger->info(sprintf('Found "%s" email origin. Id: %d.', (string)$result, $result->getId()));
        }

        return $result;
    }

    /**
     * Performs a synchronization of emails for the given email origin.
     *
     * @param EmailOrigin                           $origin   The email origin
     * @param SynchronizationProcessorSettings|null $settings The synchronization processor settings
     *
     * @throws \Exception
     *
     * @return void
     */
    protected function doSyncOrigin(EmailOrigin $origin, SynchronizationProcessorSettings $settings = null): void
    {
        $this->impersonateOrganization($origin->getOrganization());
        try {
            $processor = $this->createSynchronizationProcessor($origin);
            if ($processor instanceof LoggerAwareInterface) {
                $processor->setLogger($this->logger);
            }
        } catch (\Exception $ex) {
            $this->logger->error(sprintf('Skip origin synchronization. Error: %s', $ex->getMessage()));
            $this->setOriginSyncStateToFailed($origin);
            throw $ex;
        }

        try {
            //ref:adbrain changed for testing purpose only, very IMP revert later SYNC_CODE_SUCCESS to SYNC_CODE_IN_PROCESS!
            if ($this->changeOriginSyncState($origin, self::SYNC_CODE_IN_PROCESS)) {
                $syncStartTime = $this->getCurrentUtcDateTime();
                if ($settings) {
                    $processor->setSettings($settings);
                }
                $processor->process($origin, $syncStartTime, new EmailSyncNotificationBag());
                $this->changeOriginSyncState($origin, self::SYNC_CODE_SUCCESS, $syncStartTime);
            } else {
                $this->logger->info('Skip because it is already in process.');
            }
        } catch (SyncFolderTimeoutException $ex) {
            $this->logger->info($ex->getMessage());
            $this->changeOriginSyncState($origin, self::SYNC_CODE_SUCCESS);
            throw $ex;
        } catch (\Exception $ex) {
            if ((strpos($ex->getMessage(), "No valid API key or access_token provided.") !== false) || (strpos($ex->getMessage(), "Includes authentication errors, blocked developer applications, and cancelled accounts.") !== false) || (strpos($ex->getMessage(), "An error occurred in the Nylas server. If this persists, please see our status page or contact support.") !== false) || (strpos($ex->getMessage(), "resource has been removed from our servers.") !== false) || (strpos($ex->getMessage(), "timed out making an external request. Please try again.") !== false) || (strpos($ex->getMessage(), "The requested item doesn't exist") !== false)) {
                $this->setOriginSyncStateToFailed($origin);
            } elseif ((strpos($ex->getMessage(), "cURL error 35: OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to api.nylas.com:443") !== false)) {
                $this->setSyncStateToManualSync($origin);
            } else {
                if ((strpos($ex->getMessage(), "some issue found when calling nylas.") !== false) || (strpos($ex->getMessage(), "cURL error 6: Could not resolve host: api.nylas.com") !== false)) {
                    // Ignore the errors
                } else {
                    $this->appendLog($ex, $origin);
                }
                // Enable if you want to change the sync code to 4 with unmaze exceptions.
                $this->setSyncStateToCustomException($origin);
            }

            $this->logger->error(
                sprintf('The synchronization failed here. Error: %s', $ex->getMessage()),
                ['exception' => $ex]
            );

            throw $ex;
        }
    }

    /**
     * Attempts to sets the state of a given email origin to Exception.
     *
     * @param EmailOrigin $origin The email origin
     *
     * @return void
     */
    protected function setSyncStateToManualSync(EmailOrigin $origin): void
    {
        try {
            $this->changeOriginSyncState($origin, self::SYNC_CODE_SUCCESS);
        } catch (\Exception $innerEx) {
            // ignore any exception here
            $this->logger->error(
                sprintf('Cannot set the fail state. Error: %s', $innerEx->getMessage()),
                ['exception' => $innerEx]
            );
        }
    }

    /**
     * Attempts to sets the state of a given email origin to Exception.
     *
     * @param EmailOrigin $origin The email origin
     *
     * @return void
     */
    protected function setSyncStateToCustomException(EmailOrigin $origin): void
    {
        try {
            $this->changeOriginSyncState($origin, self::SYNC_CODE_SUCCESS);
        } catch (\Exception $innerEx) {
            // ignore any exception here
            $this->logger->error(
                sprintf('Cannot set the fail state. Error: %s', $innerEx->getMessage()),
                ['exception' => $innerEx]
            );
        }
    }

    /**
     * Add logs about the exception generated while sync emails and status goes to 4.
     *
     * @param \Exception  $exception The exception
     * @param EmailOrigin $origin    The email origin
     *
     * @return void
     */
    public function appendLog(\Exception $exception, EmailOrigin $origin): void
    {
        try {
            $data = [
                'subDomain' => $this->clientIdentifier,
                'emailOrigin' => $origin->getMailboxName(),
                'error' => $exception->getMessage(),
                'timeZone' => $this->localeSettings->getTimeZone(),
            ];

            $originsResponse = $this->getEntityManager()->getRepository(NylasEmailOrigin::class)->postNylasException($this->managerUrl, $data);
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf('Cannot add logs. Error: %s', $exception->getMessage()),
                ['exception' => $exception]
            );
        }
    }
}
