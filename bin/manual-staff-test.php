<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Oliverbj\Cord\Cord;

define('CORD_MANUAL_ROOT', dirname(__DIR__));
define('TESTBENCH_WORKING_PATH', CORD_MANUAL_ROOT);

require CORD_MANUAL_ROOT.'/vendor/autoload.php';

if (is_file(CORD_MANUAL_ROOT.'/.env')) {
    Dotenv::createImmutable(CORD_MANUAL_ROOT)->safeLoad();
}

$options = getopt('', [
    'connection::',
    'payload::',
    'company::',
    'enterprise::',
    'server::',
    'send',
    'sender-id::',
    'recipient-id::',
    'disable-code-mapping',
    'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, <<<'TXT'
Usage:
  php bin/manual-staff-test.php [--connection=NTG_TRN] [--company=CPH] [--payload=resources/manual/staff-payload.local.php] [--send]

Defaults:
  --connection=base
  --payload=resources/manual/staff-payload.local.php

Behavior:
  Without --send the script only prints the XML.
  With --send it posts the payload to the configured eAdapter connection.
  EnterpriseID and ServerID are derived from the configured CORD_URL unless overridden.

TXT);

    exit(0);
}

$connection = (string) ($options['connection'] ?? 'base');
$payloadOption = (string) ($options['payload'] ?? 'resources/manual/staff-payload.local.php');
$payloadPath = isAbsolutePath($payloadOption) ? $payloadOption : CORD_MANUAL_ROOT.'/'.$payloadOption;

if (! is_file($payloadPath)) {
    fwrite(STDERR, "Payload file not found: {$payloadPath}\n");
    fwrite(STDERR, "Copy resources/manual/staff-payload.example.php to resources/manual/staff-payload.local.php and edit it locally.\n");

    exit(1);
}

/** @var mixed $payload */
$payload = require $payloadPath;

if (! is_array($payload)) {
    fwrite(STDERR, "The payload file must return a PHP array.\n");

    exit(1);
}

/** @var Application $app */
$app = require CORD_MANUAL_ROOT.'/vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ($connection !== 'base') {
    $selectedConfig = config('cord.'.$connection.'.eadapter_connection');

    if (is_array($selectedConfig)) {
        config()->set('cord.base.eadapter_connection', $selectedConfig);
    }
}

try {
    /** @var Cord $cord */
    $cord = app('cord');

    if ($connection !== 'base') {
        $cord->withConfig($connection);
    }

    if (isset($options['company'])) {
        $cord->withCompany((string) $options['company']);
    }

    if (isset($options['enterprise'])) {
        $cord->withEnterprise((string) $options['enterprise']);
    }

    if (isset($options['server'])) {
        $cord->withServer((string) $options['server']);
    }

    if (isset($options['sender-id'])) {
        $cord->withSenderId((string) $options['sender-id']);
    }

    if (isset($options['recipient-id'])) {
        $cord->withRecipientId((string) $options['recipient-id']);
    }

    if (isset($options['disable-code-mapping'])) {
        $cord->withCodeMapping(false);
    }

    $xml = $cord->addStaff($payload)->inspect();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");

    exit(1);
}

fwrite(STDOUT, "Connection: {$connection}\n");
fwrite(STDOUT, "Payload: {$payloadPath}\n\n");
fwrite(STDOUT, redactSensitiveXml($xml)."\n");

if (! isset($options['send'])) {
    fwrite(STDOUT, "\nDry run only. Re-run with --send to post this payload.\n");

    exit(0);
}

try {
    $response = $cord->run();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");

    exit(1);
}

fwrite(STDOUT, "\nResponse\n");
fwrite(STDOUT, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

function isAbsolutePath(string $path): bool
{
    return str_starts_with($path, DIRECTORY_SEPARATOR) || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
}

function redactSensitiveXml(string $xml): string
{
    return preg_replace('/<Password>.*?<\/Password>/s', '<Password>[REDACTED]</Password>', $xml) ?? $xml;
}
