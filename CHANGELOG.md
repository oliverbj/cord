# Changelog

All notable changes to `cord` will be documented in this file.

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

- Added Laravel Boost package resources: a core Cord guideline and an optional `cord-development` skill for Boost-enabled applications.

## 1.1.4 - 2023-01-24

WIP with facades..

## 1.1.1 - 2023-01-24

- Added "throw error if config values are not set"

## 1.0.0 - 2022-08-23

First release of Cord - a seamless integration experience towards CargoWise One.
