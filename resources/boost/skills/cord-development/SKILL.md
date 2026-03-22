---
name: cord-development
description: "Use when working with Cord, CargoWise eAdapter XML, fluent request builders, schema(), describe(), fromStructured(), inspect(), toJson(), toXml(), or rawXml()."
---

# Cord Development

## When to use this skill

Use this skill when you are adding or changing Cord integrations, building CargoWise eAdapter requests, or deciding between Cord's fluent builders, structured operations, and raw XML escape hatch.

## Core workflow

1. Start with `describe()` or `schema()` before generating code.
2. Prefer `fromStructured()` when you already have a normalized payload or when an AI agent needs a strict contract.
3. Prefer fluent builders when hand-written application code should stay readable and close to business intent.
4. Use `inspect()` first while iterating or testing so XML can be reviewed without sending HTTP.
5. Use `run()` only after the payload shape is correct.
6. Use `toJson()` when the caller needs a JSON string, and `toXml()` when the caller needs the original XML response.

## Contract discovery

- `Cord::describe()` lists available resources.
- `Cord::staff()->describe()` lists operations for the selected resource.
- `Cord::organization('SAGFURHEL')->get()->describe()` returns the active schema for organization retrieval.
- `Cord::oneOffQuote('QCPH00001004')->get()->describe()` returns the active schema for quote retrieval.
- `Cord::oneOffQuote()->create()->describe()` returns the active schema for the fully scoped builder.
- `Cord::schema('operation.id')` returns required fields, nested object shapes, and enums.
- Treat `fromStructured()` validation failures as contract feedback. Update the payload to match the schema instead of bypassing validation.

```php
$description = Cord::staff()->describe();

$schema = Cord::schema('staff.create');
```

## Structured vs fluent

Use `fromStructured()` when:

- input already exists as arrays or JSON-like data
- you need validation against published operation contracts
- the code path is AI-authored or dynamically assembled

Use fluent builders when:

- the request is hand-written and should stay easy to scan
- closures make nested structures clearer than arrays
- the payload maps naturally to a small number of builder calls
- one-off quote addresses should be passed as organization code strings instead of expanded address objects

```php
$xml = Cord::fromStructured('shipment.event.add', [
    'key' => 'SJFK21060014',
    'event' => [
        'date' => '2026-01-01T00:00:00+00:00',
        'type' => 'DIM',
        'reference' => 'My Reference',
        'is_estimate' => true,
    ],
])->inspect();
```

## Guardrails

- Set `withCompany()` whenever the operation depends on company context. For universal requests this also affects derived `SenderID`.
- Do not assume `sender_id` and `recipient_id` exist on every operation. Use `schema()` to confirm the supported fields.
- For `one_off_quote.create`, `event_branch` and `event_department` populate `Shipment > DataContext > EventBranch` and `EventDepartment`.
- `client_address`, `pickup_address`, and `delivery_address` on `one_off_quote.create` accept either structured address objects or plain organization code strings.
- Use `organization(...)->get()` for organization lookups and `oneOffQuote(...)->get()` for quote lookups so retrieval flows stay explicit.
- Use `oneOffQuote('QCPH00001004')->get()` or `fromStructured('one_off_quote.get', ...)` for quote lookups instead of falling back to `rawXml()`.
- Reach for `rawXml()` only when Cord does not already expose the request shape through fluent or structured APIs.
- For `rawXml()` requests, remember that `run()` returns the full parsed envelope, not only `Data`.
- `rawXml()->toJson()->run()` returns that full envelope as JSON.

## Escape hatch

Use `rawXml()` for unsupported or fully prebuilt payloads, then verify behavior with `inspect()` or capture the full envelope with `toJson()` or `toXml()` if needed.

```php
$response = Cord::rawXml($xml)
    ->toJson()
    ->run();
```