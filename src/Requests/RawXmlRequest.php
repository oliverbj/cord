<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Interfaces\RequestInterface;

class RawXmlRequest implements RequestInterface
{
    public function __construct(private readonly string $xmlPayload) {}

    public function schema(): array
    {
        return [];
    }

    public function xml(): string
    {
        return $this->xmlPayload;
    }
}
