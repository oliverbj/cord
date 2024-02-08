<?php

namespace Oliverbj\Cord\Requests;

class NativeOrganizationUpdate extends NativeRequest
{
    public function schema(): array
    {
        return $this->defineSchema();
    }

    private function defineSchema(): array
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
