<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

require_once __DIR__ . '/vendor/autoload.php';

$this->provideHook('director/ImportSource', '\\Icinga\\Module\\X509\\ProvidedHook\\HostsImportSource');
$this->provideHook('director/ImportSource', '\\Icinga\\Module\\X509\\ProvidedHook\\ServicesImportSource');