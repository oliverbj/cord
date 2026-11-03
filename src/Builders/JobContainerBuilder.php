<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class JobContainerBuilder extends OneOffQuoteContainerBuilder
{
    #[StructuredField]
    public function number(string $value): self
    {
        $this->payload['containerNumber'] = $value;

        return $this;
    }

    #[StructuredField]
    public function seal(string $value): self
    {
        $this->payload['seal'] = $value;

        return $this;
    }
}
