<?php

namespace Oliverbj\Cord\Attributes;

use Attribute;
use Oliverbj\Cord\Enums\OperationId;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class OperationField
{
    /**
     * @param  array<string, mixed>|null  $schema
     * @param  array<int, string>  $enum
     */
    public function __construct(
        public OperationId $operation,
        public ?string $name = null,
        public bool $required = false,
        public bool $repeatable = false,
        public ?array $schema = null,
        public ?string $builder = null,
        public ?string $description = null,
        public array $enum = [],
        public int $order = 0,
    ) {}
}
