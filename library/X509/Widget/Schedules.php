<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Widget;

use Icinga\Module\X509\Common\Links;
use Icinga\Module\X509\Model\X509Schedule;
use ipl\Html\Table;
use ipl\I18n\Translation;
use ipl\Orm\Query;
use ipl\Web\Widget\EmptyStateBar;
use ipl\Web\Widget\Link;

class Schedules extends Table
{
    use Translation;

    protected $defaultAttributes = [
        'class'            => 'common-table table-row-selectable',
        'data-base-target' => '_next'
    ];

    /** @var Query */
    protected $schedules;

    public function __construct(Query $schedules)
    {
        $this->schedules = $schedules;
    }

    protected function assemble(): void
    {
        /** @var X509Schedule $schedule */
        foreach ($this->schedules as $schedule) {
            $row = static::tr();
            $row->addHtml(
                static::td(new Link($schedule->name, Links::updateSchedule($schedule))),
                static::td($schedule->author),
                static::td($schedule->ctime->format('Y-m-d H:i')),
                static::td($schedule->mtime->format('Y-m-d H:i'))
            );

            $this->addHtml($row);
        }

        if ($this->isEmpty()) {
            $this->setTag('div');
            $this->addHtml(new EmptyStateBar($this->translate('No job schedules.')));
        } else {
            $row = static::tr();
            $row->addHtml(
                static::th($this->translate('Name')),
                static::th($this->translate('Author')),
                static::th($this->translate('Date Created')),
                static::th($this->translate('Date Modified'))
            );
            $this->getHeader()->addHtml($row);
        }
    }
}
