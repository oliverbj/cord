<?php

namespace Oliverbj\Cord\Schema;

use Illuminate\Validation\ValidationException;

class SchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $configuredFields
     */
    public function validate(array $schema, array $payload, array $configuredFields = []): void
    {
        $errors = [];

        $this->validateValue($schema, $payload, '', $errors, array_fill_keys($configuredFields, true), true);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, bool>  $configuredFields
     */
    private function validateValue(array $schema, mixed $value, string $path, array &$errors, array $configuredFields = [], bool $isRoot = false): void
    {
        if ($this->shouldSkipTypeValidation($schema)) {
            if (($schema['type'] ?? null) === 'object' && is_array($value)) {
                $this->validateObject($schema, $value, $path, $errors, $configuredFields, $isRoot);
            }

            if (($schema['type'] ?? null) === 'array' && is_array($value) && isset($schema['items']) && is_array($schema['items'])) {
                $this->validateArray($schema, $value, $path, $errors);
            }

            return;
        }

        if (! $this->matchesType($schema['type'], $value)) {
            $errors[$path === '' ? 'payload' : $path][] = 'The field must match the expected type.';

            return;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[$path === '' ? 'payload' : $path][] = 'The field must be one of the allowed values.';
        }

        if (($schema['type'] ?? null) === 'object' && is_array($value)) {
            $this->validateObject($schema, $value, $path, $errors, $configuredFields, $isRoot);
        }

        if (($schema['type'] ?? null) === 'array' && is_array($value)) {
            $this->validateArray($schema, $value, $path, $errors);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<int|string, mixed>  $value
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, bool>  $configuredFields
     */
    private function validateObject(array $schema, array $value, string $path, array &$errors, array $configuredFields, bool $isRoot): void
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $additionalProperties = $schema['additionalProperties'] ?? true;

        foreach ($required as $field) {
            if (! array_key_exists($field, $value) && ! ($isRoot && isset($configuredFields[$field]))) {
                $errors[$this->joinPath($path, $field)][] = 'The field is required.';
            }
        }

        foreach ($value as $field => $item) {
            if (! array_key_exists($field, $properties)) {
                if ($additionalProperties === false) {
                    $errors[$this->joinPath($path, (string) $field)][] = 'The field is not supported.';
                }

                continue;
            }

            $propertySchema = $properties[$field];
            if (! is_array($propertySchema)) {
                continue;
            }

            $this->validateValue($propertySchema, $item, $this->joinPath($path, (string) $field), $errors);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<int|string, mixed>  $value
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateArray(array $schema, array $value, string $path, array &$errors): void
    {
        $itemSchema = $schema['items'] ?? null;
        if (! is_array($itemSchema)) {
            return;
        }

        foreach (array_values($value) as $index => $item) {
            $this->validateValue($itemSchema, $item, $this->joinPath($path, (string) $index), $errors);
        }
    }

    private function shouldSkipTypeValidation(array $schema): bool
    {
        return ! array_key_exists('type', $schema) || $schema['type'] === null;
    }

    private function matchesType(string|array $expected, mixed $value): bool
    {
        $types = is_array($expected) ? $expected : [$expected];

        foreach ($types as $type) {
            if ($this->matchesNamedType($type, $value)) {
                return true;
            }
        }

        return false;
    }

    private function matchesNamedType(string $type, mixed $value): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ! array_is_list($value),
            'null' => $value === null,
            default => true,
        };
    }

    private function joinPath(string $prefix, string $segment): string
    {
        return $prefix === '' ? $segment : $prefix.'.'.$segment;
    }
}
