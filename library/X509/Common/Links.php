<?php

// SPDX-FileCopyrightText: 2023 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\X509\Common;

use Icinga\Module\X509\Model\X509Job;
use Icinga\Module\X509\Model\X509Schedule;
use ipl\Web\Url;

class Links
{
    public static function job(X509Job $job): Url
    {
        return Url::fromPath('x509/job', ['id' => $job->id]);
    }

    public static function updateJob(X509Job $job): Url
    {
        return Url::fromPath('x509/job/update', ['id' => $job->id]);
    }

    public static function schedules(X509Job $job): Url
    {
        return Url::fromPath('x509/job/schedules', ['id' => $job->id]);
    }

    public static function scheduleJob(X509Job $job): Url
    {
        return Url::fromPath('x509/job/schedule', ['id' => $job->id]);
    }

    public static function updateSchedule(X509Schedule $schedule): Url
    {
        return Url::fromPath('x509/job/update-schedule', ['id' => $schedule->job->id, 'scheduleId' => $schedule->id]);
    }
}
