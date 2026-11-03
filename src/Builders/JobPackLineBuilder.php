<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class JobPackLineBuilder extends OneOffQuotePackLineBuilder
{
    #[StructuredField]
    public function description(string $value): self
    {
        $this->payload['description'] = $value;

        return $this;
    }

    #[StructuredField]
    public function commodity(string $code): self
    {
        $this->payload['commodityCode'] = $code;

        return $this;
    }

    #[StructuredField(name: 'hs_code')]
    public function hsCode(string $value): self
    {
        $this->payload['hsCode'] = $value;

        return $this;
    }

    #[StructuredField(name: 'marks_and_numbers')]
    public function marksAndNumbers(string $value): self
    {
        $this->payload['marksAndNumbers'] = $value;

        return $this;
    }
}
