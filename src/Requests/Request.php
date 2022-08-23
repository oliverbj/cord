<?php

namespace Oliverbj\Cord\Requests;
use Oliverbj\Cord\Interfaces\RequestInterface;
use Oliverbj\Cord\Cord;
use Spatie\ArrayToXml\ArrayToXml;

abstract class Request implements RequestInterface
{
    protected string $rootElement;
    protected string $subRootElement;

    public function __construct(public Cord $cord){
        //Get the root element from the class name
        $this->rootElement = substr(strrchr(get_class($this), '\\'), 1);
        //The rootElement contains a name with the prefix "Universal" - remove it.
        $this->subRootElement = str_replace('Universal', '', $this->rootElement);
    }

    protected function context(): array
    {
        return [
            $this->subRootElement => [
                'DataContext' => [
                    'DataTargetCollection' => [
                        'DataTarget' => [
                            'Type' => $this->cord->target->value,
                            'Key' => $this->cord->targetKey,
                        ],
                    ],
                ],
            ]
        ];
    }

    protected function build(array $schema) : array
    {

        //1. Add the "DataContext" key to the schema.
        $context = $this->context();

        //Get the first key of the schema (It can be "ShipmentRequest", "DocumentRequest" etc.)
        //If supplying "EnterpriseID", "ServerID" and "Company.Code", it should be present under the "DataContext" key,
        $key = key($context);
        $DataTargetArray = $context[$key];

        //2. Append the "EnterpriseID", "ServerID" and "Company.Code" to the "DataContext" key.
        if($this->cord->company){
            $DataTargetArray['DataContext'] += [
                'EnterpriseID' => $this->cord->enterprise,
                'ServerID' => $this->cord->server,
                'Company' => [
                    'Code' => $this->cord->company,
                ],
            ];
        }


        //3. Append any filters to the "FilterCollection" key.
        if(! empty($this->cord->filters)){
            $filters = [];
            foreach($this->cord->filters as $filter){
                $filters[] = $filter;
            }

            $DataTargetArray['FilterCollection'] = [
                'Filter' => $filters,
            ];

        }

        //4. Add the schema defined by the XXXRequest class if any.
        $DataTargetArray += $schema;

        return [
            $key => $DataTargetArray,
        ];
    }


    public function xml() : string
    {
        $array = $this->build($this->schema());

        $xml = ArrayToXml::convert($array, $this->rootElement);
        //Remove the "<?xml version="1.0".. " tag from the XML string.
        return preg_replace('!^[^>]+>(\r\n|\n)!','', $xml);

    }

}
