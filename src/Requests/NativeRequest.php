<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Cord;
use Oliverbj\Cord\Interfaces\RequestInterface;
use Spatie\ArrayToXml\ArrayToXml;

abstract class NativeRequest implements RequestInterface
{
    protected string $rootElement = 'Native';

    public function __construct(public Cord $cord)
    {
    }

    public function xml(): string
    {
        $xml = ArrayToXml::convert($this->schema(), [
            'rootElementName' => $this->rootElement,
            '_attributes' => [
                'xmlns' => 'http://www.cargowise.com/Schemas/Native',
            ],
        ]);

        //Remove the "<?xml version="1.0".. " tag from the XML string.
        return preg_replace('!^[^>]+>(\r\n|\n)!', '', $xml);
    }
}
