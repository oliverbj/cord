<?php

namespace Oliverbj\Cord\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class StructuredField
{
    /**
     * @param  array<string, mixed>|null  $schema
     * @param  array<int, string>  $enum
     */
    public function __construct(
        public ?string $name = null,
        public bool $required = false,
        public ?array $schema = null,
        public ?string $description = null,
        public array $enum = [],
        public int $order = 0,
    ) {}
}
