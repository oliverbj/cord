<?php

namespace Oliverbj\Cord\Requests;

class NativeCompanyRetrival extends NativeRequest
{
    public function schema(): array
    {
        return $this->defineSchema($this->cord->criteriaGroups);
    }

    private function defineSchema(array $criteriaGroups): array
    {
        $schema = [
            'Body' => [
                'Company' => [],
            ],
        ];

        array_push($schema['Body']['Company'], $criteriaGroups[0]);

        return $schema;
    }
}
