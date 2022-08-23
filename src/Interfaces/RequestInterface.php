<?php

namespace Oliverbj\Cord\Interfaces;

interface RequestInterface
{
    public function schema(): array;

    public function xml(): string;
}
