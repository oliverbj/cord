# Cord

Cord provides a fluent Laravel API for sending CargoWise One eAdapter requests over HTTP.

## Installation and configuration

- Install the package with `composer require oliverbj/cord`.
- Publish the config with `php artisan vendor:publish --tag=cord-config`.
- Configure `CORD_URL`, `CORD_USERNAME`, and `CORD_PASSWORD`, or define named connections in `config/cord.php` and select them with `withConfig('name')`.

## Preferred request flow

- Start from a target such as `shipment()`, `booking()`, `custom()`, `organization()`, `company()`, `staff()`, `oneOffQuote()`, or `receivable()`.
- Call `run()` to send the request. Cord returns parsed array data by default.
- Call `inspect()` while iterating or testing to build XML without sending any HTTP request.
- Call `toXml()` before `run()` only when the caller needs the original XML response.

## AI-friendly operation helpers

- Prefer `describe()`, `schema()`, and `fromStructured()` when generating Cord integrations from normalized input or when the required fields are not yet known.
- `Cord::describe()` lists published resources.
- `Cord::resource()->describe()` lists operations for the selected resource.
- `Cord::schema('operation.id')` returns the JSON-Schema-like contract for the operation, including required fields, nested objects, and enums.
- `Cord::fromStructured('operation.id', $payload)` validates input before XML generation. Fix validation errors instead of bypassing the schema.

```php
$schema = Cord::schema('one_off_quote.create');

$xml = Cord::fromStructured('one_off_quote.create', [
    'company' => 'CPH',
    'branch' => 'A01',
    'department' => 'FES',
    'transport_mode' => 'SEA',
    'port_of_origin' => 'AUSYD',
    'port_of_destination' => 'NZAKL',
])->inspect();
```

## Company context and identifiers

- Use `withCompany()` for operations that depend on company context.
- For universal requests, `withCompany()` also lets Cord derive `SenderID`. The default `RecipientID` is `Cord`.
- Override sender and recipient identifiers only when the integration explicitly requires custom values.
- Do not assume every operation accepts `sender_id` and `recipient_id`; use `schema()` to confirm the supported fields for the selected operation.

## Raw XML and response shape

- Use `rawXml()` only when you already have a complete XML document or need a request shape Cord does not model yet.
- For `rawXml()` requests, `run()` returns the full parsed eAdapter envelope with `Status`, `ProcessingLog`, and `Data`.
- `rawXml()->toXml()->run()` returns the full XML response envelope.

```php
$response = Cord::withConfig('archive')
    ->rawXml($xml)
    ->run();

$status = $response['Status'];
```