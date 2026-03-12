<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Enums\DataTarget;

class UniversalShipmentRequest extends Request
{
    public function schema(): array
    {
        if ($this->cord->target === DataTarget::OneOffQuote) {
            return $this->cord->oneOffQuote;
        }

        return [];
    }
}
