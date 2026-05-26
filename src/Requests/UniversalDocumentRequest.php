<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Enums\DataTarget;

class UniversalDocumentRequest extends Request
{
    protected function context(): array
    {
        $context = parent::context();

        if ($this->cord->target !== DataTarget::DocManager) {
            return $context;
        }

        if (! is_string($this->cord->company) || trim($this->cord->company) === '') {
            throw new \Exception('Company code must be provided for DocManager requests. Call withCompany() before sending the request.');
        }

        $enterpriseId = $this->cord->resolveEnterpriseId();
        $serverId = $this->cord->resolveServerId();

        if (! $enterpriseId || ! $serverId) {
            throw new \Exception('EnterpriseID and ServerID could not be derived from the configured URL. Use a CargoWise URL like https://demo1trnservices.example.invalid/eAdaptor or override with withEnterprise() and withServer().');
        }

        $key = key($context);

        if (! is_string($key)) {
            return $context;
        }

        $context[$key]['DataContext'] = array_replace_recursive($context[$key]['DataContext'], [
            'Company' => [
                'Code' => $this->cord->company,
            ],
            'EnterpriseID' => $enterpriseId,
            'ServerID' => $serverId,
        ]);

        return $context;
    }

    protected function shouldIncludeInterchangeContext(): bool
    {
        if ($this->cord->target === DataTarget::DocManager) {
            return false;
        }

        return parent::shouldIncludeInterchangeContext();
    }

    public function schema(): array
    {
        return [];
    }
}
