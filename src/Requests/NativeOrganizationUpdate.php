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
                    'OrgHeader' => [
                        'Code' => $this->cord->targetKey,
                        'OrgAddressCollection' => [
                            'OrgAddress' => $this->cord->address,
                        ],
                        'OrgContactCollection' => [
                            'OrgContact' => $this->cord->contact,
                        ],
                    ],
                ],
            ],
        ];

        return $schema;
    }
}
