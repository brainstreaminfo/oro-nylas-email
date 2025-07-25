<?php


namespace BrainStream\Bundle\NylasBundle\Command\Cron;

use BrainStream\Bundle\NylasBundle\Sync\NylasEmailSynchronizer;
use Oro\Bundle\CronBundle\Command\CronCommandScheduleDefinitionInterface;
use Oro\Bundle\EmailBundle\Sync\Model\SynchronizationProcessorSettings;
use Oro\Component\Log\OutputLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EmailSyncCommand extends Command implements CronCommandScheduleDefinitionInterface
{
    protected static $defaultName = 'oro:cron:nylas-sync';

    private const MAX_TASKS = -1;
    private const MAX_CONCURRENT_TASKS = 3;
    private const MIN_EXEC_INTERVAL_IN_MIN = 5;
    private const MAX_EXEC_TIME_IN_MIN = 5;
    private const MAX_JOBS_COUNT = 2;

    private NylasEmailSynchronizer $synchronizer;

    public function __construct(NylasEmailSynchronizer $synchronizer)
    {
        $this->synchronizer = $synchronizer;
        parent::__construct();
    }

    public function getDefaultDefinition(): string
    {
        return '*/1 * * * *'; // Runs every minute
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Synchronize emails via Nylas API')
            ->addOption(
                'max-concurrent-tasks',
                null,
                InputOption::VALUE_OPTIONAL,
                'The maximum number of synchronization tasks running at the same time.',
                self::MAX_CONCURRENT_TASKS
            )
            ->addOption(
                'min-exec-interval',
                null,
                InputOption::VALUE_OPTIONAL,
                'The minimum time interval (in minutes) between two synchronizations of the same email origin.',
                self::MIN_EXEC_INTERVAL_IN_MIN
            )
            ->addOption(
                'max-exec-time',
                null,
                InputOption::VALUE_OPTIONAL,
                'The maximum execution time (in minutes). -1 for unlimited.',
                self::MAX_EXEC_TIME_IN_MIN
            )
            ->addOption(
                'max-tasks',
                null,
                InputOption::VALUE_OPTIONAL,
                'The maximum number of email origins to synchronize. -1 for unlimited.',
                self::MAX_TASKS
            )
            ->addOption(
                'id',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'The identifier of email origin to synchronize.'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force mode: resync all emails for checked folders. Use with --id only.'
            )
            ->addOption(
                'vvv',
                null,
                InputOption::VALUE_NONE,
                'Show log messages during resync.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->synchronizer->setLogger(new OutputLogger($output));

        $force = $input->getOption('force');
        $showMessage = $input->getOption('vvv');
        $originIds = $input->getOption('id');

        if ($force && empty($originIds)) {
            $this->writeAttentionMessageForOptionForce($output);
            return Command::FAILURE;
        }

        if (!empty($originIds)) {
            $settings = new SynchronizationProcessorSettings($force, $showMessage);
            //dump($originIds);
            $this->synchronizer->syncOrigins($originIds, $settings);
        } else {
            $this->synchronizer->sync(
                (int)$input->getOption('max-concurrent-tasks'),
                (int)$input->getOption('min-exec-interval'),
                (int)$input->getOption('max-exec-time'),
                (int)$input->getOption('max-tasks')
            );
        }

        return Command::SUCCESS;
    }

    private function writeAttentionMessageForOptionForce(OutputInterface $output): void
    {
        $output->writeln([
            '<comment>ATTENTION</comment>: The option "force" can be used only for specific email origins.',
            '           Use the "id" option with the required email origin IDs in the command line.'
        ]);
    }
}
