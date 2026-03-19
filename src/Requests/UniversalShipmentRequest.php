<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Enums\DataTarget;

class UniversalShipmentRequest extends Request
{
    protected function context(): array
    {
        $context = parent::context();

        if (! $this->isOneOffQuoteRequest()) {
            return $context;
        }

        $key = key($context);

        if (! is_string($key)) {
            return $context;
        }

        if (! is_string($this->cord->company) || trim($this->cord->company) === '') {
            throw new \Exception('Company code must be provided for one-off quote requests. Call withCompany() before sending the request.');
        }

        $enterpriseId = $this->cord->resolveEnterpriseId();
        $serverId = $this->cord->resolveServerId();

        if (! $enterpriseId || ! $serverId) {
            throw new \Exception('EnterpriseID and ServerID could not be derived from the configured URL. Use a CargoWise URL like https://demo1trnservices.example.invalid/eAdaptor or override with withEnterprise() and withServer().');
        }

        $dataContext = [
            'Company' => [
                'Code' => $this->cord->company,
            ],
            'EnterpriseID' => $enterpriseId,
            'ServerID' => $serverId,
        ];

        if ($this->isOneOffQuoteQuery()) {
            $dataContext['RecipientRoleCollection'] = [
                'RecipientRole' => [
                    'Code' => 'ORP',
                ],
            ];
        }

        $context[$key]['DataContext'] = array_replace_recursive($context[$key]['DataContext'], $dataContext);

        return $context;
    }

    protected function shouldIncludeInterchangeContext(): bool
    {
        if ($this->isOneOffQuoteRequest()) {
            return false;
        }

        return parent::shouldIncludeInterchangeContext();
    }

    public function schema(): array
    {
        if ($this->cord->target === DataTarget::OneOffQuote) {
            if ($this->cord->activeOneOffQuoteIntent() === 'create') {
                return [
                    'Shipment' => $this->cord->oneOffQuote,
                ];
            }

            return $this->cord->oneOffQuote;
        }

        return [];
    }

    private function isOneOffQuoteQuery(): bool
    {
        return $this->isOneOffQuoteRequest()
            && $this->cord->activeOneOffQuoteIntent() === 'get'
            && is_string($this->cord->targetKey)
            && trim($this->cord->targetKey) !== '';
    }

    private function isOneOffQuoteRequest(): bool
    {
        return $this->cord->target === DataTarget::OneOffQuote;
    }
}
