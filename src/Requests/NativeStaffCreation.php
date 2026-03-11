<?php

namespace Oliverbj\Cord\Requests;

class NativeStaffCreation extends NativeRequest
{
    public function schema(): array
    {
        return [
            'Header' => $this->cord->nativeHeader(),
            'Body' => [
                'Staff' => [
                    '_attributes' => ['version' => '1.0'],
                    'GlbStaff' => $this->cord->staff,
                ],
            ],
        ];
    }
}
