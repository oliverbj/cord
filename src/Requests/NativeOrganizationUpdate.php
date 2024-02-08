<?php

namespace Oliverbj\Cord\Requests;

class NativeOrganizationUpdate extends NativeRequest
{
    public function schema(): array
    {
        return $this->defineSchema($this->cord->address);
    }

    private function defineSchema(array $address): array
    {

        $schema = [
            'Body' => [
                'Organization' => [
                    'Code' => $this->cord->targetKey,
                    'OrgAddressCollection' => [
                        'OrgAddress' => $this->cord->address,
                    ],
                ],
            ],
        ];

        return $schema;
    }
}
