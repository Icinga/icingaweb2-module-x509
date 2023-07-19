<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Widget;

use Icinga\Module\X509\Common\Links;
use Icinga\Module\X509\Model\X509Job;
use ipl\Html\Table;
use ipl\I18n\Translation;
use ipl\Orm\Query;
use ipl\Web\Widget\EmptyState;
use ipl\Web\Widget\Link;

class Jobs extends Table
{
    use Translation;

    /** @var Query */
    protected $jobs;

    protected $defaultAttributes = [
        'class'            => 'common-table table-row-selectable',
        'data-base-target' => '_next'
    ];

    public function __construct(Query $jobs)
    {
        $this->jobs = $jobs;
    }

    protected function assemble(): void
    {
        $jobs = $this->jobs->execute();
        if (! $jobs->hasResult()) {
            $this->addHtml(new EmptyState($this->translate('No jobs configured yet.')));
            return;
        }

        $headers = static::tr();
        $headers->addHtml(
            static::th($this->translate('Name')),
            static::th($this->translate('Author')),
            static::th($this->translate('Date Created')),
            static::th($this->translate('Date Modified'))
        );
        $this->getHeader()->addHtml($headers);

        /** @var X509Job $job */
        foreach ($jobs as $job) {
            $row = static::tr();
            $row->addHtml(
                static::td(new Link($job->name, Links::job($job))),
                static::td($job->author),
                static::td($job->ctime->format('Y-m-d H:i')),
                static::td($job->mtime->format('Y-m-d H:i'))
            );

            $this->addHtml($row);
        }
    }
}
