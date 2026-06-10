<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Cord;
use Oliverbj\Cord\Interfaces\RequestInterface;
use Spatie\ArrayToXml\ArrayToXml;

abstract class NativeRequest implements RequestInterface
{
    protected string $rootElement = 'Native';

    public function __construct(public Cord $cord) {}

    public function xml(): string
    {
        return ArrayToXml::convert($this->schema(), [
            'rootElementName' => $this->rootElement,
            '_attributes' => [
                'xmlns' => 'http://www.cargowise.com/Schemas/Native',
            ],
        ], true, 'UTF-8');
    }
}
