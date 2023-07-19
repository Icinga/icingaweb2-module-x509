<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Clicommands;

use DateTime;
use Icinga\Application\Logger;
use Icinga\Authentication\Auth;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Job;
use Icinga\Repository\IniRepository;
use Icinga\User;
use Icinga\Util\Json;
use ipl\Scheduler\Cron;
use ipl\Sql\Connection;
use ipl\Sql\Expression;
use stdClass;

use function ipl\Stdlib\get_php_type;

class MigrateCommand extends Command
{
    /**
     * Migrate the jobs config rom INI to the database
     *
     * USAGE
     *
     *     icingacli x509 migrate jobs --author=<name>
     *
     * OPTIONS
     *
     * --author=<name>
     *     An Icinga Web 2 user used to mark as an author for all the migrated jobs.
     */
    public function jobsAction(): void
    {
        /** @var string $author */
        $author = $this->params->getRequired('author');
        /** @var User $user */
        $user = Auth::getInstance()->getUser();
        $user->setUsername($author);

        $this->migrateJobs();

        Logger::info('Successfully applied all pending migrations');
    }

    protected function migrateJobs(): void
    {
        $repo = new class () extends IniRepository {
            /** @var array<string, array<int, string>> */
            protected $queryColumns = [
                'jobs' => ['name', 'cidrs', 'ports', 'exclude_targets', 'schedule', 'frequencyType']
            ];

            /** @var array<string, array<string, string>> */
            protected $configs = [
                'jobs' => [
                    'module'    => 'x509',
                    'name'      => 'jobs',
                    'keyColumn' => 'name'
                ]
            ];
        };

        $conn = $this->getDb();
        $conn->transaction(function (Connection $conn) use ($repo) {
            /** @var User $user */
            $user = Auth::getInstance()->getUser();
            /** @var stdClass $data */
            foreach ($repo->select() as $data) {
                $config = [];
                if (! isset($data->frequencyType) && ! empty($data->schedule)) {
                    $frequency = new Cron($data->schedule);
                    $config = [
                        'type'      => get_php_type($frequency),
                        'frequency' => Json::encode($frequency)
                    ];
                } elseif (! empty($data->schedule)) {
                    $config = [
                        'type'      => $data->frequencyType,
                        'frequency' => $data->schedule // Is already json encoded
                    ];
                }

                $excludes = $data->exclude_targets;
                if (empty($excludes)) {
                    $excludes = new Expression('NULL');
                }

                $conn->insert('x509_job', [
                    'name'            => $data->name,
                    'author'          => $user->getUsername(),
                    'cidrs'           => $data->cidrs,
                    'ports'           => $data->ports,
                    'exclude_targets' => $excludes,
                    'ctime'           => (new DateTime())->getTimestamp() * 1000,
                    'mtime'           => (new DateTime())->getTimestamp() * 1000
                ]);

                $jobId = (int) $conn->lastInsertId();
                if (! empty($config)) {
                    $config['rescan'] = 'n';
                    $config['full_scan'] = 'n';
                    $config['since_last_scan'] = Job::DEFAULT_SINCE_LAST_SCAN;

                    $conn->insert('x509_schedule', [
                        'job_id' => $jobId,
                        'name'   => $data->name . ' Schedule',
                        'author' => $user->getUsername(),
                        'config' => Json::encode($config),
                        'ctime'  => (new DateTime())->getTimestamp() * 1000,
                        'mtime'  => (new DateTime())->getTimestamp() * 1000,
                    ]);
                }
            }
        });
    }
}
