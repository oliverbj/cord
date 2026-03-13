<?php

namespace Oliverbj\Cord\Schema;

use Oliverbj\Cord\Enums\OperationId;

class OperationDefinition
{
    /**
     * @param  array<int, string>  $contextFields
     * @param  array<int, string>  $requiredContextFields
     * @param  array<string, mixed>|null  $selector
     * @param  array<int, string>  $bootstrapMethods
     */
    public function __construct(
        public readonly OperationId $id,
        public readonly string $resource,
        public readonly string $action,
        public readonly array $contextFields = [],
        public readonly array $requiredContextFields = [],
        public readonly ?array $selector = null,
        public readonly array $bootstrapMethods = [],
    ) {}
}
