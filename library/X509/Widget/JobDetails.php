<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Widget;

use Icinga\Module\X509\Model\X509JobRun;
use ipl\Html\Table;
use ipl\I18n\Translation;
use ipl\Orm\Query;
use ipl\Web\Widget\EmptyStateBar;

class JobDetails extends Table
{
    use Translation;

    protected $defaultAttributes = ['class' => 'common-table'];

    /** @var Query */
    protected $runs;

    public function __construct(Query $runs)
    {
        $this->runs = $runs;
    }

    protected function assemble(): void
    {
        /** @var X509JobRun $run */
        foreach ($this->runs as $run) {
            $row = static::tr();
            $row->addHtml(
                static::td($run->job->name),
                static::td($run->schedule->name ?: $this->translate('N/A')),
                static::td((string) $run->total_targets),
                static::td((string) $run->finished_targets),
                static::td($run->start_time->format('Y-m-d H:i')),
                static::td($run->end_time ? $run->end_time->format('Y-m-d H:i') : 'N/A')
            );

            $this->addHtml($row);
        }

        if ($this->isEmpty()) {
            $this->setTag('div');
            $this->addHtml(new EmptyStateBar($this->translate('Job never run.')));
        } else {
            $row = static::tr();
            $row->addHtml(
                static::th($this->translate('Name')),
                static::th($this->translate('Schedule Name')),
                static::th($this->translate('Total')),
                static::th($this->translate('Scanned')),
                static::th($this->translate('Started')),
                static::th($this->translate('Finished'))
            );

            $this->getHeader()->addHtml($row);
        }
    }
}
