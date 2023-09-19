<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

/** @var \Icinga\Application\Modules\Module $this */

$this->provideHook('DbMigration', '\\Icinga\\Module\\X509\\ProvidedHook\\DbMigration');

$this->provideHook('director/ImportSource', '\\Icinga\\Module\\X509\\ProvidedHook\\HostsImportSource');
$this->provideHook('director/ImportSource', '\\Icinga\\Module\\X509\\ProvidedHook\\ServicesImportSource');
