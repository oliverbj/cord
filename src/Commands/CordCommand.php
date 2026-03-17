<?php

namespace Oliverbj\Cord\Commands;

use Illuminate\Console\Command;
use Oliverbj\Cord\Cord;

class CordCommand extends Command
{
    public $signature = 'cord:staff:test
        {--connection=base : Cord connection name from config/cord.php}
        {--payload=resources/manual/staff-payload.local.php : Relative path to a local PHP payload file}
        {--company= : Company code used for native DataContext and derived SenderID}
        {--enterprise= : Override the EnterpriseID instead of deriving it from the URL}
        {--server= : Override the ServerID instead of deriving it from the URL}
        {--update : Update an existing staff row instead of creating one}
        {--send : Actually send the request to eAdapter}
        {--force : Skip the send confirmation prompt}
        {--disable-code-mapping : Disable native code mapping for this request}';

    public $description = 'Inspect or manually send a staff create/update request using a named Cord connection.';

    public function handle(): int
    {
        $payloadPath = $this->resolvePayloadPath($this->option('payload'));

        if (! is_file($payloadPath)) {
            $this->error("Payload file not found: {$payloadPath}");
            $this->line('Copy `resources/manual/staff-payload.example.php` to `resources/manual/staff-payload.local.php` and edit it locally.');

            return self::INVALID;
        }

        /** @var mixed $payload */
        $payload = require $payloadPath;

        if (! is_array($payload)) {
            $this->error('The payload file must return a PHP array.');

            return self::INVALID;
        }

        try {
            $connection = (string) $this->option('connection');

            if ($connection !== 'base') {
                $selectedConfig = config('cord.'.$connection.'.eadapter_connection');

                if (is_array($selectedConfig)) {
                    config()->set('cord.base.eadapter_connection', $selectedConfig);
                }
            }

            /** @var Cord $cord */
            $cord = app('cord');

            if ($connection !== 'base') {
                $cord->withConfig($connection);
            }

            if ($company = $this->option('company')) {
                $cord->withCompany((string) $company);
            } elseif (isset($payload['company'])) {
                $cord->withCompany((string) $payload['company']);
            }

            if ($enterprise = $this->option('enterprise')) {
                $cord->withEnterprise((string) $enterprise);
            }

            if ($server = $this->option('server')) {
                $cord->withServer((string) $server);
            }

            if ($this->option('disable-code-mapping')) {
                $cord->withCodeMapping(false);
            }

            $builder = $this->option('update')
                ? $cord->staff((string) ($payload['code'] ?? ''))->update()
                : $cord->staff()->create();

            if (isset($payload['code'])) {
                $builder->code((string) $payload['code']);
            }

            if (isset($payload['loginName'])) {
                $builder->loginName((string) $payload['loginName']);
            }

            if (isset($payload['password'])) {
                $builder->password((string) $payload['password']);
            }

            if (isset($payload['fullName'])) {
                $builder->fullName((string) $payload['fullName']);
            }

            if (isset($payload['email'])) {
                $builder->email((string) $payload['email']);
            }

            if (isset($payload['homeBranch'])) {
                $builder->branch((string) $payload['homeBranch']);
            }

            if (isset($payload['homeDepartment'])) {
                $builder->department((string) $payload['homeDepartment']);
            }

            if (isset($payload['active'])) {
                $builder->isActive((bool) $payload['active']);
            }

            if (isset($payload['country'])) {
                $builder->country((string) $payload['country']);
            }

            if (isset($payload['phone'])) {
                $builder->phone((string) $payload['phone']);
            } elseif (isset($payload['workPhone'])) {
                $builder->phone((string) $payload['workPhone']);
            }

            if (isset($payload['addressLine1'])) {
                $builder->addressLine1((string) $payload['addressLine1']);
            } elseif (isset($payload['addressOne'])) {
                $builder->addressLine1((string) $payload['addressOne']);
            }

            if (isset($payload['groups']) && is_array($payload['groups'])) {
                $builder->replaceGroups($payload['groups']);
            }

            $groupsToRemove = $payload['groupsToRemove'] ?? $payload['removeGroups'] ?? null;
            if (is_array($groupsToRemove)) {
                foreach ($groupsToRemove as $groupCode) {
                    if (is_string($groupCode)) {
                        $builder->removeGroup($groupCode);
                    }
                }
            }

            if (isset($payload['attributes']) && is_array($payload['attributes'])) {
                $builder->withPayload($payload['attributes']);
            }

            $xml = $builder->inspect();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Resolved request');
        $this->line('Connection: '.(string) $this->option('connection'));
        $this->line('Mode: '.($this->option('update') ? 'update' : 'create'));
        $this->line('Payload: '.$payloadPath);
        $this->newLine();
        $this->line($this->redactSensitiveXml($xml));

        if (! $this->option('send')) {
            $this->newLine();
            $this->comment('Dry run only. Re-run with `--send` to post this payload.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Send this request to the configured eAdapter connection?', false)) {
            $this->comment('Aborted before sending.');

            return self::SUCCESS;
        }

        try {
            $response = $cord->run();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Response');
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: (string) $response);

        return self::SUCCESS;
    }

    private function resolvePayloadPath(string $payloadPath): string
    {
        if ($this->isAbsolutePath($payloadPath)) {
            return $payloadPath;
        }

        return base_path($payloadPath);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    private function redactSensitiveXml(string $xml): string
    {
        return preg_replace('/<Password>.*?<\/Password>/s', '<Password>[REDACTED]</Password>', $xml) ?? $xml;
    }
}
