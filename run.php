<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/** @var \Icinga\Application\Modules\Module $this */

$this->provideHook('DbMigration', '\\Icinga\\Module\\X509\\ProvidedHook\\DbMigration');

$this->provideHook('director/ImportSource', '\\Icinga\\Module\\X509\\ProvidedHook\\HostsImportSource');
$this->provideHook('director/ImportSource', '\\Icinga\\Module\\X509\\ProvidedHook\\ServicesImportSource');
