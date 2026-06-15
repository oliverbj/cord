---
name: cord-development
description: "Use when working with Cord, CargoWise eAdapter XML, fluent request builders, docManager(), schema(), describe(), fromStructured(), inspect(), toJson(), toXml(), or rawXml()."
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
7. Use `select()` before `run()` when only a subset of response fields is needed. Accepts varargs or a single array; dot notation supported for nested keys.

## Contract discovery

- `Cord::describe()` lists available resources.
- `Cord::staff()->describe()` lists operations for the selected resource.
- `Cord::staff('BVO')->get()->describe()` returns the active schema for staff retrieval.
- `Cord::organization('SAGFURHEL')->get()->describe()` returns the active schema for organization retrieval.
- `Cord::oneOffQuote('QCPH00001004')->get()->describe()` returns the active schema for quote retrieval.
- `Cord::withCompany('QHE')->docManager('QU1', 'QHEL00011452')->describe()` returns the active schema for a DocManager lookup.
- `Cord::oneOffQuote()->create()->describe()` returns the active schema for the fully scoped builder.
- `Cord::schema('operation.id')` returns required fields, nested object shapes, and enums.
- Treat `fromStructured()` validation failures as contract feedback. Update the payload to match the schema instead of bypassing validation.

```php
$description = Cord::staff()->describe();

$querySchema = Cord::schema('staff.query');

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
    'event_contexts' => [
        ['type' => 'MBLNumber', 'value' => 'HBL85161TRN'],
        ['type' => 'BOLNumber', 'value' => '423908'],
    ],
])->inspect();
```

## Guardrails

- Set `withCompany()` whenever the operation depends on company context. For universal requests this also affects derived `SenderID`.
- Do not assume `sender_id` and `recipient_id` exist on every operation. Use `schema()` to confirm the supported fields.
- For `staff.query`, use `GlbStaff` as the native criteria entity, or call `staff('CODE')->get()` to preload a key lookup by `Code`.
- For `container.query`, use `GlbContainerType` as the native criteria entity, or call `container('20GP')->get()` to preload a key lookup by `Code`.
- For `staff.create` and `staff.update`, `can_login` maps to CargoWise `CanLogin`; create defaults to `true` when omitted, and update only sends the field when explicitly provided.
- For `one_off_quote.create`, `branch` populates both `Shipment > DataContext > Branch` and `Shipment > JobCosting > Branch`.
- For `one_off_quote.create`, `org_role` populates `Shipment > OrgRole`; use `LOC` for Local Client and `OAG` for Overseas Agent.
- For `one_off_quote.create`, `packing_mode` populates `Shipment > ContainerMode > Code`; use values such as `FCL`, `LCL`, `FTL`, or `LSE`.
- For `one_off_quote.create`, `commodity` populates `Shipment > LocalProcessing > Commodity > Code`; passing a code like `GEN` is sufficient.
- For `one_off_quote.create`, `event_branch` and `event_department` populate `Shipment > DataContext > EventBranch` and `EventDepartment`.
- For `one_off_quote.create`, `carrier_address` adds an `OrganizationAddress` with `AddressType=ShippingLineAddress`; passing a string like `DHLAIR_WW` sets `OrganizationCode`.
- For `one_off_quote.create`, `overseas_agent_address` adds an `OrganizationAddress` with `AddressType=SendersOverseasAgent`; passing a string like `MAIATLSAV` sets `OrganizationCode`.
- Use `addPotentialCarrier()` or structured `potential_carriers` on `one_off_quote.create` to populate `PotentialCarrierCollection > PotentialCarrier > Code` with one or more carrier organization codes such as `KLMAIR_WW` and `LUFAIR_WW`.
- Use `addNote()` or structured `notes` on `one_off_quote.create` to populate `Shipment > NoteCollection > Note`. `key` becomes `Description`, `text` becomes `NoteText`, `IsCustomDescription` is always `false`, and `NoteContext` is fixed to `AAA / Module: A - All; Direction: A - All; Freight: A - All`.
- `client_address`, `pickup_address`, `delivery_address`, `carrier_address`, and `overseas_agent_address` on `one_off_quote.create` accept either structured address objects or plain organization code strings.
- When using an address object, `address_line_1` is required unless `address_override: true` is also set. With `address_override: true`, only `city` and `country` are required.
- Use `addPackLine()` or structured `pack_lines` on `one_off_quote.create` to attach individual packing lines. Each pack line requires `pack_type` and `quantity`; `weight`, `volume`, `length`, `width`, `height`, and `description` are optional. CargoWise uses the shared `LengthUnit` element for dimensions, so Cord omits `WidthUnit` and `HeightUnit` from outbound pack line XML.
- Use `addContainer()` or structured `containers` on `one_off_quote.create` to attach containers for FCL shipments. Each container requires `type` (e.g. `20GP`); `count` (defaults to `1`), `type_description`, `iso_code`, and `category` (`['code' => 'DRY', 'description' => 'Dry Storage']`) are optional. Maps to `ContainerCollection > Container` in XML.
- Use `addDocument()` or structured `one_off_quote.document.add` to attach a document to an existing one-off quote. This runs as a `UniversalEvent` request and requires `withCompany()` plus a quote key so `Event > DataContext` includes `Company`, `EnterpriseID`, and `ServerID`. Do not confuse this with `addAttachedDocument()` on `one_off_quote.create`, which attaches documents inline at creation time.
- Use `addEvent()` or structured `one_off_quote.event.add` to push an event to an existing one-off quote. This also runs as a `UniversalEvent` request and requires `withCompany()` plus a quote key so `Event > DataContext` includes `Company`, `EnterpriseID`, and `ServerID`.
- Use `addEventContext('Type', 'Value')` (repeatable) or structured `event_contexts` on event add operations to populate `Event > ContextCollection > Context` rows.
- Use `docManager('MODULE', 'JOBNUMBER')` or `fromStructured('doc_manager.get', [...])` for DocManager document lookups. Cord composes `DataTarget > Key` as `<MODULE> <JOBNUMBER>` and sends `Company`, `EnterpriseID`, and `ServerID` inside `DocumentRequest > DataContext`.
- Use `filter()` for a single document `FilterCollection`, or `filterCollection()` / structured `filter_collections` when CargoWise expects multiple distinct `FilterCollection` nodes in the same document request.
- DocManager requests require `withCompany()` and do not support top-level `sender_id` / `recipient_id`.
- CargoWise does not support one-off quote updates through eAdapter, so Cord does not publish a `one_off_quote.update` operation. Do not generate `oneOffQuote('KEY')->update()` or `fromStructured('one_off_quote.update', ...)` flows.
- Use `organization(...)->get()` for organization lookups, `staff(...)->get()` for staff lookups, `container(...)->get()` for container type lookups, and `oneOffQuote(...)->get()` for quote lookups so retrieval and write flows stay explicit.
- Use `docManager('QU1', 'QHEL00011452')` for DocManager lookups instead of falling back to `rawXml()`.
- Use `oneOffQuote('QCPH00001004')->get()` or `fromStructured('one_off_quote.get', ...)` for quote lookups instead of falling back to `rawXml()`.
- Reach for `rawXml()` only when Cord does not already expose the request shape through fluent or structured APIs.
- For `rawXml()` requests, remember that `run()` returns the full parsed envelope, not only `Data`.
- `rawXml()->toJson()->run()` returns that full envelope as JSON.
- Use `select('Field', ...)` before `run()` to return only a subset of fields from any response. Accepts varargs or a single array; dot notation (e.g. `'Owner.Code'`) is supported for nested keys. Composes with `toJson()`.

## Escape hatch

Use `rawXml()` for unsupported or fully prebuilt payloads, then verify behavior with `inspect()` or capture the full envelope with `toJson()` or `toXml()` if needed.

```php
$response = Cord::rawXml($xml)
    ->toJson()
    ->run();
```