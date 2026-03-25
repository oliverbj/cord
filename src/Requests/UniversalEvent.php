<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Enums\OperationId;

class UniversalEvent extends Request
{
    protected function context(): array
    {
        $context = parent::context();

        if (! $this->isDocumentAddRequest()) {
            return $context;
        }

        $key = key($context);

        if (! is_string($key)) {
            return $context;
        }

        if ($this->isOneOffQuoteDocumentAdd()
            && (! is_string($this->cord->company) || trim($this->cord->company) === '')) {
            throw new \Exception('Company code must be provided for one-off quote document add requests. Call withCompany() before sending the request.');
        }

        if (! is_string($this->cord->company) || trim($this->cord->company) === '') {
            return $context;
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
        ]);

        return $context;
    }

    protected function shouldIncludeInterchangeContext(): bool
    {
        if ($this->isOneOffQuoteDocumentAdd()) {
            return false;
        }

        if ($this->isDocumentAddRequest()
            && is_string($this->cord->company)
            && trim($this->cord->company) !== ''
            && $this->cord->resolveEnterpriseId() !== null
            && $this->cord->resolveServerId() !== null) {
            return false;
        }

        return parent::shouldIncludeInterchangeContext();
    }

    public function schema(): array
    {
        return $this->cord->document;
    }

    private function isDocumentAddRequest(): bool
    {
        return in_array($this->cord->currentOperation, [
            OperationId::ShipmentDocumentAdd,
            OperationId::BookingDocumentAdd,
            OperationId::CustomDocumentAdd,
            OperationId::OneOffQuoteDocumentAdd,
        ], true);
    }

    private function isOneOffQuoteDocumentAdd(): bool
    {
        return $this->cord->currentOperation === OperationId::OneOffQuoteDocumentAdd;
    }
}
