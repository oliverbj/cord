<?php

namespace Oliverbj\Cord\Schema;

use Closure;
use Illuminate\Support\Str;
use Oliverbj\Cord\Attributes\OperationField;
use Oliverbj\Cord\Attributes\StructuredField;
use Oliverbj\Cord\Builders\OneOffQuoteAddressBuilder;
use Oliverbj\Cord\Builders\OneOffQuoteAttachedDocumentBuilder;
use Oliverbj\Cord\Builders\OneOffQuoteChargeLineBuilder;
use Oliverbj\Cord\Cord;
use Oliverbj\Cord\Enums\DataTarget;
use Oliverbj\Cord\Enums\OperationId;
use Oliverbj\Cord\Enums\RequestType;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

class OperationRegistry
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $operationFieldCache = [];

    /** @var array<string, array<string, mixed>> */
    private array $builderSchemaCache = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $builderFieldCache = [];

    /** @var array<string, OperationDefinition>|null */
    private ?array $definitions = null;

    public function definition(string|OperationId $operation): OperationDefinition
    {
        $id = $operation instanceof OperationId ? $operation : OperationId::from($operation);

        return $this->definitions()[$id->value];
    }

    /**
     * @return array<string, OperationDefinition>
     */
    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $nativeWriteContext = ['config', 'company', 'enterprise', 'server', 'sender_id', 'recipient_id', 'code_mapping'];
        $universalContext = ['config', 'company'];

        return $this->definitions = [
            OperationId::ShipmentGet->value => new OperationDefinition(
                id: OperationId::ShipmentGet,
                resource: 'shipment',
                action: 'get',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'shipment', 'required' => true, 'type' => 'string'],
            ),
            OperationId::BookingGet->value => new OperationDefinition(
                id: OperationId::BookingGet,
                resource: 'booking',
                action: 'get',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'booking', 'required' => true, 'type' => 'string'],
            ),
            OperationId::CustomGet->value => new OperationDefinition(
                id: OperationId::CustomGet,
                resource: 'custom',
                action: 'get',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'custom', 'required' => true, 'type' => 'string'],
            ),
            OperationId::ShipmentDocumentsGet->value => new OperationDefinition(
                id: OperationId::ShipmentDocumentsGet,
                resource: 'shipment',
                action: 'documents.get',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'shipment', 'required' => true, 'type' => 'string'],
                bootstrapMethods: ['withDocuments'],
            ),
            OperationId::BookingDocumentsGet->value => new OperationDefinition(
                id: OperationId::BookingDocumentsGet,
                resource: 'booking',
                action: 'documents.get',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'booking', 'required' => true, 'type' => 'string'],
                bootstrapMethods: ['withDocuments'],
            ),
            OperationId::CustomDocumentsGet->value => new OperationDefinition(
                id: OperationId::CustomDocumentsGet,
                resource: 'custom',
                action: 'documents.get',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'custom', 'required' => true, 'type' => 'string'],
                bootstrapMethods: ['withDocuments'],
            ),
            OperationId::ReceivableDocumentsGet->value => new OperationDefinition(
                id: OperationId::ReceivableDocumentsGet,
                resource: 'receivable',
                action: 'documents.get',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'receivable', 'required' => true, 'type' => 'string'],
                bootstrapMethods: ['withDocuments'],
            ),
            OperationId::ShipmentEventAdd->value => new OperationDefinition(
                id: OperationId::ShipmentEventAdd,
                resource: 'shipment',
                action: 'event.add',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'shipment', 'required' => true, 'type' => 'string'],
            ),
            OperationId::BookingEventAdd->value => new OperationDefinition(
                id: OperationId::BookingEventAdd,
                resource: 'booking',
                action: 'event.add',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'booking', 'required' => true, 'type' => 'string'],
            ),
            OperationId::CustomEventAdd->value => new OperationDefinition(
                id: OperationId::CustomEventAdd,
                resource: 'custom',
                action: 'event.add',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'custom', 'required' => true, 'type' => 'string'],
            ),
            OperationId::ShipmentDocumentAdd->value => new OperationDefinition(
                id: OperationId::ShipmentDocumentAdd,
                resource: 'shipment',
                action: 'document.add',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'shipment', 'required' => true, 'type' => 'string'],
            ),
            OperationId::BookingDocumentAdd->value => new OperationDefinition(
                id: OperationId::BookingDocumentAdd,
                resource: 'booking',
                action: 'document.add',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'booking', 'required' => true, 'type' => 'string'],
            ),
            OperationId::CustomDocumentAdd->value => new OperationDefinition(
                id: OperationId::CustomDocumentAdd,
                resource: 'custom',
                action: 'document.add',
                contextFields: $universalContext,
                selector: ['field' => 'key', 'method' => 'custom', 'required' => true, 'type' => 'string'],
            ),
            OperationId::OrganizationQuery->value => new OperationDefinition(
                id: OperationId::OrganizationQuery,
                resource: 'organization',
                action: 'query',
                selector: ['field' => 'code', 'method' => 'organization', 'required' => false, 'type' => 'string'],
            ),
            OperationId::CompanyQuery->value => new OperationDefinition(
                id: OperationId::CompanyQuery,
                resource: 'company',
                action: 'query',
                selector: ['field' => 'code', 'method' => 'company', 'required' => false, 'type' => 'string'],
            ),
            OperationId::OrganizationAddressAdd->value => new OperationDefinition(
                id: OperationId::OrganizationAddressAdd,
                resource: 'organization',
                action: 'address.add',
                contextFields: $nativeWriteContext,
                requiredContextFields: ['company'],
                selector: ['field' => 'code', 'method' => 'organization', 'required' => true, 'type' => 'string'],
            ),
            OperationId::OrganizationContactAdd->value => new OperationDefinition(
                id: OperationId::OrganizationContactAdd,
                resource: 'organization',
                action: 'contact.add',
                contextFields: $nativeWriteContext,
                requiredContextFields: ['company'],
                selector: ['field' => 'code', 'method' => 'organization', 'required' => true, 'type' => 'string'],
            ),
            OperationId::OrganizationEdiCommunicationAdd->value => new OperationDefinition(
                id: OperationId::OrganizationEdiCommunicationAdd,
                resource: 'organization',
                action: 'edi_communication.add',
                contextFields: $nativeWriteContext,
                requiredContextFields: ['company'],
                selector: ['field' => 'code', 'method' => 'organization', 'required' => true, 'type' => 'string'],
            ),
            OperationId::OrganizationAddressTransfer->value => new OperationDefinition(
                id: OperationId::OrganizationAddressTransfer,
                resource: 'organization',
                action: 'address.transfer',
                contextFields: $nativeWriteContext,
                requiredContextFields: ['company'],
                selector: ['field' => 'code', 'method' => 'organization', 'required' => true, 'type' => 'string'],
            ),
            OperationId::OrganizationContactTransfer->value => new OperationDefinition(
                id: OperationId::OrganizationContactTransfer,
                resource: 'organization',
                action: 'contact.transfer',
                contextFields: $nativeWriteContext,
                requiredContextFields: ['company'],
                selector: ['field' => 'code', 'method' => 'organization', 'required' => true, 'type' => 'string'],
            ),
            OperationId::OrganizationEdiCommunicationTransfer->value => new OperationDefinition(
                id: OperationId::OrganizationEdiCommunicationTransfer,
                resource: 'organization',
                action: 'edi_communication.transfer',
                contextFields: $nativeWriteContext,
                requiredContextFields: ['company'],
                selector: ['field' => 'code', 'method' => 'organization', 'required' => true, 'type' => 'string'],
            ),
            OperationId::OrganizationDocumentTrackingTransfer->value => new OperationDefinition(
                id: OperationId::OrganizationDocumentTrackingTransfer,
                resource: 'organization',
                action: 'document_tracking.transfer',
                contextFields: $nativeWriteContext,
                requiredContextFields: ['company'],
                selector: ['field' => 'code', 'method' => 'organization', 'required' => true, 'type' => 'string'],
            ),
            OperationId::StaffCreate->value => new OperationDefinition(
                id: OperationId::StaffCreate,
                resource: 'staff',
                action: 'create',
                contextFields: $nativeWriteContext,
                requiredContextFields: ['company'],
                bootstrapMethods: ['create'],
            ),
            OperationId::StaffUpdate->value => new OperationDefinition(
                id: OperationId::StaffUpdate,
                resource: 'staff',
                action: 'update',
                contextFields: $nativeWriteContext,
                requiredContextFields: ['company'],
                bootstrapMethods: ['update'],
            ),
            OperationId::OneOffQuoteCreate->value => new OperationDefinition(
                id: OperationId::OneOffQuoteCreate,
                resource: 'one_off_quote',
                action: 'create',
                contextFields: ['config', 'company'],
                requiredContextFields: ['company'],
                selector: ['field' => 'key', 'method' => 'oneOffQuote', 'required' => false, 'type' => 'string'],
                bootstrapMethods: ['create'],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(string|OperationId $operation): array
    {
        $definition = $this->definition($operation);
        $properties = [];
        $required = [];

        foreach ($definition->contextFields as $field) {
            $properties[$field] = $this->contextFieldSchema($field);
        }

        foreach ($definition->requiredContextFields as $field) {
            $required[] = $field;
        }

        if ($definition->selector !== null) {
            $properties[$definition->selector['field']] = [
                'type' => $definition->selector['type'] ?? 'string',
            ];

            if (($definition->selector['required'] ?? false) === true) {
                $required[] = $definition->selector['field'];
            }
        }

        foreach ($this->operationFields($definition->id) as $field) {
            $properties[$field['name']] = $field['schema'];

            if ($field['required']) {
                $required[] = $field['name'];
            }
        }

        $required = array_values(array_unique($required));

        return array_filter([
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => $required === [] ? null : $required,
            'x-cord' => [
                'operation_id' => $definition->id->value,
                'resource' => $definition->resource,
                'action' => $definition->action,
            ],
        ], static fn ($value) => $value !== null);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function operationFields(OperationId $operation): array
    {
        if (isset($this->operationFieldCache[$operation->value])) {
            return $this->operationFieldCache[$operation->value];
        }

        $reflection = new ReflectionClass(Cord::class);
        $fields = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== Cord::class) {
                continue;
            }

            foreach ($method->getAttributes(OperationField::class) as $attribute) {
                /** @var OperationField $metadata */
                $metadata = $attribute->newInstance();

                if ($metadata->operation !== $operation) {
                    continue;
                }

                $fields[] = $this->buildOperationFieldDefinition($method, $metadata);
            }
        }

        usort($fields, static fn (array $left, array $right) => $left['order'] <=> $right['order']);

        return $this->operationFieldCache[$operation->value] = $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function builderSchema(string $builderClass): array
    {
        if (isset($this->builderSchemaCache[$builderClass])) {
            return $this->builderSchemaCache[$builderClass];
        }

        $fields = $this->builderFields($builderClass);
        $required = [];
        $properties = [];

        foreach ($fields as $field) {
            $properties[$field['name']] = $field['schema'];

            if ($field['required']) {
                $required[] = $field['name'];
            }
        }

        return $this->builderSchemaCache[$builderClass] = array_filter([
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => $required === [] ? null : array_values(array_unique($required)),
        ], static fn ($value) => $value !== null);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function builderFields(string $builderClass): array
    {
        if (isset($this->builderFieldCache[$builderClass])) {
            return $this->builderFieldCache[$builderClass];
        }

        $reflection = new ReflectionClass($builderClass);
        $fields = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $builderClass) {
                continue;
            }

            $attributes = $method->getAttributes(StructuredField::class);
            if ($attributes === []) {
                continue;
            }

            /** @var StructuredField $metadata */
            $metadata = $attributes[0]->newInstance();
            $name = $metadata->name ?? Str::snake($method->getName());
            $schema = $metadata->schema ?? $this->inferSingleInvocationSchema($method);

            if ($metadata->enum !== [] && ! isset($schema['enum'])) {
                $schema['enum'] = $metadata->enum;
            }

            if ($metadata->description !== null) {
                $schema['description'] = $metadata->description;
            }

            $fields[] = [
                'name' => $name,
                'schema' => $schema,
                'required' => $metadata->required,
                'method' => $method->getName(),
                'order' => $metadata->order === 0 ? ($method->getStartLine() ?? 0) : $metadata->order,
            ];
        }

        usort($fields, static fn (array $left, array $right) => $left['order'] <=> $right['order']);

        return $this->builderFieldCache[$builderClass] = $fields;
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public function groupedOperationList(): array
    {
        $grouped = [];

        foreach ($this->definitions() as $definition) {
            $grouped[$definition->resource][] = [
                'id' => $definition->id->value,
                'action' => $definition->action,
            ];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function operationsForResource(string $resource): array
    {
        $operations = $this->groupedOperationList()[$resource] ?? [];

        usort($operations, static fn (array $left, array $right) => strcmp($left['id'], $right['id']));

        return $operations;
    }

    public function detectCurrentOperation(Cord $cord): ?OperationId
    {
        if ($cord->currentOperation !== null) {
            return $cord->currentOperation;
        }

        if ($cord->target === DataTarget::Staff && $cord->requestType === RequestType::NativeStaffCreation) {
            return OperationId::StaffCreate;
        }

        if ($cord->target === DataTarget::Staff && $cord->requestType === RequestType::NativeStaffUpdate) {
            return OperationId::StaffUpdate;
        }

        if ($cord->target === DataTarget::OneOffQuote && $cord->activeOneOffQuoteIntent() === 'create') {
            return OperationId::OneOffQuoteCreate;
        }

        return match ($cord->requestType) {
            RequestType::NativeOrganizationRetrieval => OperationId::OrganizationQuery,
            RequestType::NativeCompanyRetrieval => OperationId::CompanyQuery,
            default => match (true) {
                $cord->target === DataTarget::Shipment && is_string($cord->targetKey) && trim($cord->targetKey) !== '' => OperationId::ShipmentGet,
                $cord->target === DataTarget::Booking && is_string($cord->targetKey) && trim($cord->targetKey) !== '' => OperationId::BookingGet,
                $cord->target === DataTarget::Custom && is_string($cord->targetKey) && trim($cord->targetKey) !== '' => OperationId::CustomGet,
                default => null,
            },
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOperationFieldDefinition(ReflectionMethod $method, OperationField $metadata): array
    {
        $name = $metadata->name ?? Str::snake($method->getName());
        $schema = $metadata->schema ?? $this->inferSingleInvocationSchema($method, $metadata->builder);

        if ($metadata->enum !== [] && ! isset($schema['enum'])) {
            $schema['enum'] = $metadata->enum;
        }

        if ($metadata->description !== null) {
            $schema['description'] = $metadata->description;
        }

        if ($metadata->repeatable) {
            $schema = [
                'type' => 'array',
                'items' => $schema,
            ];
        }

        return [
            'name' => $name,
            'schema' => $schema,
            'required' => $metadata->required,
            'method' => $method->getName(),
            'repeatable' => $metadata->repeatable,
            'builder' => $metadata->builder,
            'order' => $metadata->order === 0 ? ($method->getStartLine() ?? 0) : $metadata->order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inferSingleInvocationSchema(ReflectionMethod $method, ?string $builderClass = null): array
    {
        $parameters = $method->getParameters();

        if (count($parameters) === 1) {
            $parameter = $parameters[0];

            if ($builderClass !== null || $this->parameterIsClosure($parameter)) {
                return $this->builderSchema($builderClass ?? $this->resolveBuilderClassFromMethod($method));
            }

            return $this->parameterSchema($parameter);
        }

        $properties = [];
        $required = [];

        foreach ($parameters as $parameter) {
            $properties[Str::snake($parameter->getName())] = $this->parameterSchema($parameter);

            if (! $parameter->isOptional() && ! $parameter->allowsNull()) {
                $required[] = Str::snake($parameter->getName());
            }
        }

        return array_filter([
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => $required === [] ? null : $required,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function parameterSchema(ReflectionParameter $parameter): array
    {
        $types = $this->mapType($parameter->getType());

        if ($types === []) {
            return [];
        }

        return [
            'type' => count($types) === 1 ? $types[0] : array_values(array_unique($types)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function mapType(?ReflectionType $type): array
    {
        if ($type === null) {
            return [];
        }

        if ($type instanceof ReflectionUnionType) {
            $types = [];

            foreach ($type->getTypes() as $unionType) {
                $types = array_merge($types, $this->mapType($unionType));
            }

            return array_values(array_unique($types));
        }

        if (! $type instanceof ReflectionNamedType) {
            return [];
        }

        if (! $type->isBuiltin()) {
            return [];
        }

        return match ($type->getName()) {
            'string' => ['string'],
            'int' => ['integer'],
            'float' => ['number'],
            'bool' => ['boolean'],
            'array' => ['array'],
            'mixed' => [],
            'null' => ['null'],
            default => [],
        };
    }

    private function parameterIsClosure(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType
            && ! $type->isBuiltin()
            && $type->getName() === Closure::class;
    }

    private function resolveBuilderClassFromMethod(ReflectionMethod $method): string
    {
        return match ($method->getName()) {
            'clientAddress', 'pickupAddress', 'deliveryAddress' => OneOffQuoteAddressBuilder::class,
            'addChargeLine' => OneOffQuoteChargeLineBuilder::class,
            'addAttachedDocument' => OneOffQuoteAttachedDocumentBuilder::class,
            default => throw new \InvalidArgumentException('No builder class mapping exists for '.$method->getName().'.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFieldSchema(string $field): array
    {
        return match ($field) {
            'config', 'company', 'enterprise', 'server', 'sender_id', 'recipient_id' => ['type' => 'string'],
            'code_mapping' => ['type' => 'boolean'],
            default => [],
        };
    }
}
