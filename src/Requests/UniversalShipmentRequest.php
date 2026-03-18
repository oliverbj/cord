<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Enums\DataTarget;

class UniversalShipmentRequest extends Request
{
    protected function context(): array
    {
        $context = parent::context();

        if (! $this->isOneOffQuoteQuery()) {
            return $context;
        }

        $key = key($context);

        if (! is_string($key)) {
            return $context;
        }

        if (! is_string($this->cord->company) || trim($this->cord->company) === '') {
            throw new \Exception('Company code must be provided for one-off quote query requests. Call withCompany() before sending the request.');
        }

        $enterpriseId = $this->cord->resolveEnterpriseId();
        $serverId = $this->cord->resolveServerId();

        if (! $enterpriseId || ! $serverId) {
            throw new \Exception('EnterpriseID and ServerID could not be derived from the configured URL. Use a CargoWise URL like https://demo1trnservices.example.invalid/eAdaptor or override with withEnterprise() and withServer().');
        }

        $context[$key]['DataContext'] = array_replace_recursive($context[$key]['DataContext'], [
            'Company' => [
                'Code' => $this->cord->company,
            ],
            'EnterpriseID' => $enterpriseId,
            'ServerID' => $serverId,
            'RecipientRoleCollection' => [
                'RecipientRole' => [
                    'Code' => 'ORP',
                ],
            ],
        ]);

        return $context;
    }

    public function schema(): array
    {
        if ($this->cord->target === DataTarget::OneOffQuote) {
            return $this->cord->oneOffQuote;
        }

        return [];
    }

    private function isOneOffQuoteQuery(): bool
    {
        return $this->cord->target === DataTarget::OneOffQuote
            && $this->cord->activeOneOffQuoteIntent() !== 'create'
            && is_string($this->cord->targetKey)
            && trim($this->cord->targetKey) !== '';
    }
}
