<?php

namespace Oliverbj\Cord\Requests;

class NativeCompanyRetrieval extends NativeRequest
{
    public function schema(): array
    {
        return $this->defineSchema($this->cord->criteriaGroups);
    }

    private function defineSchema(array $criteriaGroups): array
    {
        return [
            'Body' => [
                'Company' => $criteriaGroups,
            ],
        ];
    }
}
