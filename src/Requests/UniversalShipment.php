<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Enums\DataTarget;

class UniversalShipment extends Request
{
    protected function context(): array
    {
        if (! $this->isOneOffQuoteCreate()) {
            return parent::context();
        }

        if (! is_string($this->cord->company) || trim($this->cord->company) === '') {
            throw new \Exception('Company code must be provided for one-off quote create requests. Call withCompany() before sending the request.');
        }

        $enterpriseId = $this->cord->resolveEnterpriseId();
        $serverId = $this->cord->resolveServerId();

        if (! $enterpriseId || ! $serverId) {
            throw new \Exception('EnterpriseID and ServerID could not be derived from the configured URL. Use a CargoWise URL like https://demo1trnservices.example.invalid/eAdaptor or override with withEnterprise() and withServer().');
        }

        $context = [
            'Shipment' => [
                'DataContext' => [
                    'DataTargetCollection' => [
                        'DataTarget' => [
                            'Type' => $this->cord->target->value,
                        ],
                    ],
                    'Company' => [
                        'Code' => $this->cord->company,
                    ],
                    'EnterpriseID' => $enterpriseId,
                    'ServerID' => $serverId,
                ],
            ],
        ];

        $quoteDraft = $this->cord->currentOneOffQuoteDraft();

        if (is_string($quoteDraft['eventBranch'] ?? null) && trim($quoteDraft['eventBranch']) !== '') {
            $context['Shipment']['DataContext']['EventBranch'] = [
                'Code' => $quoteDraft['eventBranch'],
            ];
        }

        if (is_string($quoteDraft['eventDepartment'] ?? null) && trim($quoteDraft['eventDepartment']) !== '') {
            $context['Shipment']['DataContext']['EventDepartment'] = [
                'Code' => $quoteDraft['eventDepartment'],
            ];
        }

        return $context;
    }

    protected function shouldIncludeInterchangeContext(): bool
    {
        if ($this->isOneOffQuoteCreate()) {
            return false;
        }

        return parent::shouldIncludeInterchangeContext();
    }

    public function schema(): array
    {
        if ($this->cord->target === DataTarget::OneOffQuote) {
            return $this->cord->oneOffQuote;
        }

        return [];
    }

    private function isOneOffQuoteCreate(): bool
    {
        return $this->cord->target === DataTarget::OneOffQuote
            && $this->cord->activeOneOffQuoteIntent() === 'create';
    }
}
