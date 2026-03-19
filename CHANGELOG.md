# Changelog

All notable changes to `cord` will be documented in this file.

## v3.0.7 - 2026-03-19

### Cord v3.0.7

Cord 3.0.7 is a patch release that fixes the One-Off Quote create envelope so CargoWise receives the expected UniversalShipmentRequest shape for quote creation.

#### Fixed

- Fixed one_off_quote.create to stop emitting top-level SenderID and RecipientID fields.
- One-Off Quote create requests now serialize ShipmentRequest as the first child of UniversalShipmentRequest, matching the CargoWise scope expected for quote creation.
- Preserved the required Company payload inside ShipmentRequest > DataContext for one_off_quote.create.

#### Changed

- Tightened the published one_off_quote.create schema so it no longer advertises enterprise, server, sender_id, or recipient_id as supported context fields.
- Standard universal shipment requests continue to support interchange IDs; the envelope suppression only applies to One-Off Quote request shapes.

#### Tests

- Added regression coverage to assert that One-Off Quote create XML starts with ShipmentRequest under UniversalShipmentRequest.
- Added regression coverage to assert that top-level SenderID and RecipientID are absent for one_off_quote.create.
- Added schema regression coverage to ensure one_off_quote.create only exposes the supported context fields.

#### Upgrade Notes

- No application code changes are required for the documented One-Off Quote create flow:

```php
Cord::fromStructured('one_off_quote.create', [
    'company' => 'CPH',
    'branch' => 'A01',
    'department' => 'FES',
    'transport_mode' => 'SEA',
    'port_of_origin' => 'AUSYD',
    'port_of_destination' => 'NZAKL',
])->run();

```
- If you were sending sender_id, recipient_id, enterprise, or server in structured one_off_quote.create payloads, remove them. They are not valid for this CargoWise request scope.

#### Summary

3.0.7 fixes a CargoWise compatibility issue in One-Off Quote creation by removing invalid top-level interchange fields from the UniversalShipmentRequest envelope while keeping Company inside ShipmentRequest > DataContext.

If you want, I can also insert this directly into CHANGELOG.md.

**Full Changelog**: https://github.com/oliverbj/cord/compare/3.0.6...3.0.7

## v3.0.6 - 2026-03-18

Removed logging from the package.

**Full Changelog**: https://github.com/oliverbj/cord/compare/3.0.5...3.0.6

## v3.0.5 - 2026-03-18

### v3.0.5 - 2026-03-18

#### Cord v3.0.5

Cord `3.0.5` is a patch release that fixes the One-Off Quote retrieval envelope so CargoWise receives the expected `UniversalShipmentRequest` shape for quote lookups.

##### Fixed

- Fixed `oneOffQuote('KEY')->get()` to stop emitting top-level `SenderID` and `RecipientID` fields.
- One-Off Quote retrievals now serialize `ShipmentRequest` as the first child of `UniversalShipmentRequest`, matching the CargoWise scope expected for quote queries.
- Preserved the required quote lookup `DataContext` payload inside `ShipmentRequest`, including `Company`, `EnterpriseID`, `ServerID`, and `RecipientRoleCollection` with `ORP`.

##### Changed

- Tightened the published `one_off_quote.get` schema so it no longer advertises `sender_id` and `recipient_id` as supported context fields.
- Standard universal shipment retrievals continue to support interchange IDs; the envelope suppression only applies to the One-Off Quote query shape.

##### Tests

- Added regression coverage to assert that One-Off Quote query XML starts with `ShipmentRequest` under `UniversalShipmentRequest`.
- Added regression coverage to assert that top-level `SenderID` and `RecipientID` are absent for `one_off_quote.get`.
- Added schema regression coverage to ensure `one_off_quote.get` exposes `enterprise` and `server`, but not `sender_id` or `recipient_id`.

##### Upgrade Notes

- No application code changes are required for the documented One-Off Quote query flow:
  
  ```php
  Cord::withCompany('CPH')
      ->oneOffQuote('QCPH00001004')
      ->get()
      ->run();
  
  
  ```
- If you built structured payloads for `one_off_quote.get`, stop sending `sender_id` and `recipient_id`; they are not valid for this CargoWise request scope.
  

##### Summary

`3.0.5` fixes a CargoWise compatibility issue in One-Off Quote retrieval by removing invalid top-level interchange fields from the query envelope while keeping the required quote-specific `DataContext` intact.

**Full Changelog**: https://github.com/oliverbj/cord/compare/3.0.4...3.0.5

## v3.0.4 - 2026-03-18

### v3.0.4 - 2026-03-18

#### Cord v3.0.4

Cord `3.0.4` is a patch release that standardizes retrieval flows so organization queries and One-Off Quote queries now use the same explicit `get()` pattern before execution.

##### Changed

- Standardized query activation for organization and One-Off Quote retrievals.
- Organization queries now require `get()` before `inspect()` or `run()`, matching the One-Off Quote retrieval flow.
- One-Off Quote retrievals continue to use `get()`, but the retrieval contract is now enforced consistently across both resources.
- Updated structured operation bootstrapping so `organization.query` also applies `get()` automatically.
- Updated README examples to reflect the explicit retrieval flow for organization queries.
- Updated Laravel Boost guidance and the `cord-development` skill to describe `organization(...)->get()` and `oneOffQuote(...)->get()` as the standard retrieval pattern.

##### Tests

- Added regression coverage to ensure organization queries require `get()` before execution.
- Added regression coverage to ensure One-Off Quote queries also require explicit `get()` before execution.
- Added coverage for active schema detection on scoped organization queries using `get()`.
- Updated query-related test coverage to reflect the standardized retrieval flow.
- Verified Boost resource tests against the updated guidance.

##### Upgrade Notes

- Organization queries should now use:
  
  ```php
  Cord::organization('SAGFURHEL')->get()->run();
  
  
  
  ```
- Organization queries built with criteria groups should now use:
  
  ```php
  Cord::organization()
      ->criteriaGroup([...], type: 'Key')
      ->get()
      ->run();
  
  
  
  ```
- One-Off Quote queries continue to use:
  
  ```php
  Cord::withCompany('CPH')
      ->oneOffQuote('QCPH00001004')
      ->get()
      ->run();
  
  
  
  ```
- Structured organization queries via `Cord::fromStructured('organization.query', [...])` continue to work and now bootstrap the explicit `get()` step automatically.
  

##### Summary

`3.0.4` makes retrieval behavior more consistent by standardizing explicit `get()` activation across organization and One-Off Quote queries, while keeping structured query support aligned and updating the package documentation accordingly.

**Full Changelog**: https://github.com/oliverbj/cord/compare/3.0.3...3.0.4

```



```
## v3.0.3 - 2026-03-18

### v3.0.3 - 2026-03-18

#### Cord v3.0.3

Cord `3.0.3` is a patch release that adds first-class support for querying One-Off Quotes through the Cord API.

##### Added

- Added One-Off Quote retrieval through `oneOffQuote('QCPH00001004')->get()`.
- Added the structured operation `one_off_quote.get` for `schema()`, `describe()`, and `fromStructured()`.
- Added request support for the One-Off Quote universal shipment query shape, including:
  - `DataTarget Type="OneOffQuote"`
  - `Company`
  - `EnterpriseID`
  - `ServerID`
  - `RecipientRoleCollection` with `ORP`
  

##### Changed

- One-Off Quote queries now use Cord's supported universal request flow instead of requiring a raw XML workaround.
- Query validation now requires company context so the expected CargoWise `DataContext` can be built correctly.
- Updated the README with One-Off Quote query examples and introspection examples.
- Updated Laravel Boost guidance and the `cord-development` skill to document `one_off_quote.get` and the new fluent query flow.

##### Tests

- Added regression coverage for:
  
  - fluent One-Off Quote query XML generation
  - structured `one_off_quote.get` XML generation
  - active schema detection for scoped One-Off Quote queries
  - parsed response handling for universal One-Off Quote query responses
  - missing company context validation for quote queries
  
- Updated Boost resource tests to verify that One-Off Quote query guidance ships with the package.
  

##### Upgrade Notes

- Use `Cord::withCompany('CPH')->oneOffQuote('QCPH00001004')->get()->run()` to query a One-Off Quote.
- For structured integrations, use `Cord::schema('one_off_quote.get')` and `Cord::fromStructured('one_off_quote.get', [...])`.
- If you previously relied on raw XML for One-Off Quote retrieval, you can now migrate to the built-in fluent or structured APIs.

##### Summary

`3.0.3` closes a notable gap in the One-Off Quote workflow by adding supported retrieval APIs, structured operation metadata, and updated documentation for both application developers and Laravel Boost users.

**Full Changelog**: https://github.com/oliverbj/cord/compare/3.0.2...3.0.3

## 3.0.2 - 2026-03-18

### v3.0.2 - 2026-03-18

#### Cord v3.0.2

Cord `3.0.2` is a patch release that adds explicit JSON response serialization for integrations that need string output instead of the default array response.

##### Added

- Added `toJson()` as a new response mode for Cord requests.
- `toJson()->run()` now returns the same successful payload that `run()` would normally return, serialized as JSON.
- For `rawXml()` requests, `toJson()->run()` returns the full parsed eAdapter envelope as JSON, including `Status`, `ProcessingLog`, and `Data`.

##### Changed

- Response mode selection is now explicit between `toJson()` and `toXml()`, with the last formatter called taking precedence.
- Updated the README with JSON response examples, including native organization query usage.
- Updated Laravel Boost guidance and the `cord-development` skill to document `toJson()` alongside `inspect()`, `toXml()`, and `rawXml()`.

##### Tests

- Added regression coverage for JSON response handling on both typed requests and `rawXml()` requests.
- Updated Boost resource tests to verify that `toJson()` documentation ships with the package.

##### Upgrade Notes

- Use `->toJson()->run()` when your integration needs a JSON string instead of Cord's default array response.
- Existing `run()` and `toXml()` behavior is unchanged unless you opt into `toJson()`.

##### Summary

`3.0.2` adds a focused response-formatting improvement for JSON-oriented integrations while keeping Cord's default array-based API and XML response mode intact.

**Full Changelog**: https://github.com/oliverbj/cord/compare/3.0.1...3.0.2

## v3.0.2 - 2026-03-18

### Cord v3.0.2

Cord `3.0.2` is a patch release that adds explicit JSON response serialization for integrations that need string output instead of the default array response.

#### Added

- Added `toJson()` as a new response mode for Cord requests.
- `toJson()->run()` now returns the same successful payload that `run()` would normally return, serialized as JSON.
- For `rawXml()` requests, `toJson()->run()` returns the full parsed eAdapter envelope as JSON, including `Status`, `ProcessingLog`, and `Data`.

#### Changed

- Response mode selection is now explicit between `toJson()` and `toXml()`, with the last formatter called taking precedence.
- Updated the README with JSON response examples, including native organization query usage.
- Updated Laravel Boost guidance and the `cord-development` skill to document `toJson()` alongside `inspect()`, `toXml()`, and `rawXml()`.

#### Tests

- Added regression coverage for JSON response handling on both typed requests and `rawXml()` requests.
- Updated Boost resource tests to verify that `toJson()` documentation ships with the package.

#### Upgrade Notes

- Use `->toJson()->run()` when your integration needs a JSON string instead of Cord's default array response.
- Existing `run()` and `toXml()` behavior is unchanged unless you opt into `toJson()`.

#### Summary

`3.0.2` adds a focused response-formatting improvement for JSON-oriented integrations while keeping Cord's default array-based API and XML response mode intact.

## v3.0.1 - 2026-03-18

### Cord v3.0.1

Cord `3.0.1` is a patch release focused on installation stability and package registration behavior after `3.0.0`.

#### Fixed

- Fixed a package discovery issue that could cause `composer require oliverbj/cord` to fail in some Laravel applications during `artisan package:discover`.
- Removed early console kernel resolution from Cord's service provider, which could trigger container bootstrapping problems too early in the application lifecycle.
- Switched command registration to Laravel's standard deferred command registration flow.

#### Changed

- Config publishing is now exposed only through the package-specific publish tag:
  
  - `php artisan vendor:publish --tag="cord-config"`
  
- Cord no longer registers its config under Laravel's broad `config` publish tag.
  
- Added regression test coverage for:
  
  - service provider command registration
  - package config publishing behavior
  

#### Upgrade Notes

- If you ran into install or package discovery issues on `3.0.0`, upgrade to `3.0.1`.
- No application code changes should be required for most users.
- If you publish the package config, use:

```bash
php artisan vendor:publish --tag="cord-config"






```
#### Summary

`3.0.1` is a stability patch for `3.0.0` that makes package installation and discovery safer in host Laravel applications, while keeping the new Cord 3 feature set intact.

## v3.0.0 - new major release - 2026-03-18

### Cord v3.0.0

Cord 3 is a major release that significantly expands the package beyond the `2.0.1` feature set.

This release adds schema-driven request building, richer CargoWise workflows for organizations, staff, and one-off quotes, raw XML support, better debugging and manual testing tools, and Laravel Boost integration for AI-assisted development.

#### Highlights

##### Structured operations and validation

Cord now includes a schema-driven API for discovering and validating supported operations.

New capabilities include:

- `describe()` to inspect available resources and operations
- `schema()` to retrieve a JSON-Schema-like contract for an operation
- `fromStructured()` to build requests from validated structured payloads

This makes Cord much easier to use in dynamic integrations, internal tooling, and AI-assisted workflows where request shapes need to be discovered instead of hardcoded.

##### One-off quote support

Cord now supports one-off quote creation, including fluent builders for:

- addresses
- charge lines
- attached documents

This is a major addition that did not exist in `2.0.1`.

##### Expanded organization support

Organization handling has been significantly expanded.

New capabilities include:

- organization creation
- unified organization querying through `organization()`
- organization address updates and transfers
- organization contact updates and transfers
- organization EDI communication updates and transfers
- organization document tracking transfer support

Compared to `2.0.1`, this turns organizations from a narrower retrieval flow into a much more complete integration surface.

##### Improved staff workflows

Cord now supports richer native staff operations, including:

- dedicated native staff update handling
- improved staff payload generation
- group replacement and group removal support
- stronger validation and broader test coverage for staff requests

##### Raw XML support

Cord now supports sending fully prebuilt XML payloads with `rawXml()`.

This includes:

- validation of raw XML payloads before sending
- returning the full parsed eAdapter envelope for raw XML requests
- returning the full XML response envelope when `toXml()` is used

This provides a clean escape hatch for advanced integrations that need full control over the request body.

##### Better request context and identifier handling

Sender and recipient handling for universal requests has been improved and clarified.

Cord now better supports:

- company-scoped requests with `withCompany()`
- derived `SenderID` values for universal requests
- explicit sender and recipient overrides when required

##### Better debugging and manual testing

Cord 3 improves the day-to-day developer workflow with:

- stronger `inspect()`-based dry-run flows
- a manual staff test command and local payload workflow
- expanded automated coverage across the package

##### Laravel Boost support

Cord now ships Laravel Boost resources directly from the package, including:

- a core guideline for Cord conventions and request flow
- an optional `cord-development` skill for on-demand Cord-specific guidance

Applications using Laravel Boost can pick these resources up automatically through `php artisan boost:install` and `php artisan boost:update`.

#### Breaking Changes

This release raises the supported platform baseline.

Cord 3 now requires:

- PHP `8.2+`
- Laravel `11.x` or `12.x`

That means support for PHP `8.1` and Laravel `9.x` / `10.x` has been dropped.

In addition, older organization retrieval integrations should be reviewed. The package now centers organization queries around `organization()` instead of the older retrieval-specific flow used in earlier versions.

#### Upgrade Notes

If you are upgrading from `2.0.1`:

- ensure your application is running PHP `8.2` or newer
- ensure your Laravel application is on `11.x` or `12.x`
- review older organization retrieval code and migrate it to the current `organization()` API
- consider adopting `describe()`, `schema()`, and `fromStructured()` if you want schema-driven request generation
- if your application uses Laravel Boost, run `php artisan boost:install` or `php artisan boost:update` after upgrading

#### Summary

Compared to `2.0.1`, Cord 3 adds:

- schema-driven operation discovery and validation
- one-off quote creation
- richer organization create, update, and transfer flows
- improved staff update and group management support
- raw XML request handling
- better sender and recipient context handling
- improved debugging and manual testing workflows
- Laravel Boost guidelines and skills

Cord 3 is the most capable Cord release so far and lays the groundwork for both richer CargoWise integrations and better AI-assisted development workflows.

## Unreleased

- Nothing yet.

## 1.1.4 - 2023-01-24

WIP with facades..

## 1.1.1 - 2023-01-24

- Added "throw error if config values are not set"

## 1.0.0 - 2022-08-23

First release of Cord - a seamless integration experience towards CargoWise One.
