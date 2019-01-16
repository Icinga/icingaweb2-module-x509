<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Date\DateFormatter;
use ipl\Html\ValidHtml;

/**
 * Format time as time ago via DateFormatter::timeAgo()
 */
class TimeAgoWidget implements ValidHtml
{
    /**
     * @var int
     */
    private $time;

    /**
     * @var bool
     */
    private $timeOnly;

    /**
     * Constructor
     *
     * @param int $time
     * @param bool $timeOnly
     */
    public function __construct($time, $timeOnly = false)
    {
        $this->time = $time;
        $this->timeOnly = $timeOnly;
    }

    public function render()
    {
        if (! $this->time) {
            return '';
        }

        return sprintf(
            '<span class="relative-time time-ago" title="%s">%s</span>',
            DateFormatter::formatDateTime($this->time),
            DateFormatter::timeAgo($this->time, $this->timeOnly)
        );
    }
}
