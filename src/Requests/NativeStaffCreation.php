<?php

namespace Oliverbj\Cord\Requests;

class NativeStaffCreation extends NativeRequest
{
    public function schema(): array
    {
        return [
            'Header' => [
                'DataContext' => [
                    'CodesMappedToTarget' => $this->cord->enableCodeMapping ? 'true' : 'false',
                    'Company' => [
                        'Code' => $this->cord->company,
                    ],
                    'EnterpriseID' => $this->cord->resolveEnterpriseId(),
                    'ServerID' => $this->cord->resolveServerId(),
                ],
            ],
            'Body' => [
                'Staff' => [
                    '_attributes' => ['version' => '1.0'],
                    'GlbStaff' => $this->cord->staff,
                ],
            ],
        ];
    }
}
