<?php

namespace Oliverbj\Cord\Requests;

use Oliverbj\Cord\Cord;
use Oliverbj\Cord\Interfaces\RequestInterface;
use Spatie\ArrayToXml\ArrayToXml;

abstract class Request implements RequestInterface
{
    protected string $rootElement;

    protected string $subRootElement;

    public function __construct(public Cord $cord)
    {
        // Get the root element from the class name
        $this->rootElement = substr(strrchr(get_class($this), '\\'), 1);
        // The rootElement contains a name with the prefix "Universal" - remove it.
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
            ],
        ];
    }

    protected function interchangeContext(): array
    {
        if (! $this->shouldIncludeInterchangeContext()) {
            return [];
        }

        return [
            'SenderID' => $this->cord->resolveSenderId(),
            'RecipientID' => $this->cord->resolveRecipientId(),
        ];
    }

    protected function shouldIncludeInterchangeContext(): bool
    {
        $senderId = $this->cord->senderId;
        if (is_string($senderId) && trim($senderId) !== '') {
            return true;
        }

        $recipientId = $this->cord->recipientId;
        if (trim($recipientId) !== '' && $recipientId !== 'Cord') {
            return true;
        }

        return $this->cord->company !== null
            && trim($this->cord->company) !== ''
            && $this->cord->resolveEnterpriseId() !== null
            && $this->cord->resolveServerId() !== null;
    }

    protected function build(array $schema): array
    {
        // 1. Add the "DataContext" key to the schema.
        $context = $this->context();

        // Get the first key of the schema (It can be "ShipmentRequest", "DocumentRequest" etc.)
        $key = key($context);
        $DataTargetArray = $context[$key];

        // 2. Append the company code to the DataContext key.
        if ($this->cord->company) {
            $DataTargetArray['DataContext'] += [
                'Company' => [
                    'Code' => $this->cord->company,
                ],
            ];
        }

        // 3. Append any filters to the "FilterCollection" key.
        $filterCollections = [];

        if (! empty($this->cord->filters)) {
            $filterCollections[] = [
                'Filter' => $this->cord->filters,
            ];
        }

        foreach ($this->cord->filterCollections as $collection) {
            $filterCollections[] = [
                'Filter' => $collection,
            ];
        }

        if ($filterCollections !== []) {
            $DataTargetArray['FilterCollection'] = count($filterCollections) === 1
                ? $filterCollections[0]
                : $filterCollections;
        }

        // 4. (if any), add an event to request.
        if (! empty($this->cord->event)) {
            $DataTargetArray['EventTime'] = $this->cord->event['EventTime'];
            $DataTargetArray['EventType'] = $this->cord->event['EventType'];
            $DataTargetArray['EventReference'] = $this->cord->event['EventReference'];
            $DataTargetArray['IsEstimate'] = $this->cord->event['IsEstimate'];

            if ($this->cord->eventContexts !== []) {
                $DataTargetArray['ContextCollection'] = [
                    'Context' => count($this->cord->eventContexts) === 1
                        ? $this->cord->eventContexts[0]
                        : $this->cord->eventContexts,
                ];
            }
        }

        // 5. Add the schema defined by the XXXRequest class if any.
        $DataTargetArray += $schema;

        return array_merge(
            $this->interchangeContext(),
            [
                $key => $DataTargetArray,
            ]
        );
    }

    public function xml(): string
    {
        $array = $this->build($this->schema());

        return ArrayToXml::convert($array, $this->rootElement, true, 'UTF-8');
    }
}
