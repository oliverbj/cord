<?php

namespace Oliverbj\Cord\Requests;

class UniversalEvent extends Request
{
    public function schema(): array
    {
        return $this->cord->document;
        return [];
    }
}
