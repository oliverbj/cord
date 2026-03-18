# Seamless integration to CargoWise One's eAdapter using Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oliverbj/cord.svg?style=flat-square)](https://packagist.org/packages/oliverbj/cord)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/oliverbj/cord/run-tests?label=tests)](https://github.com/oliverbj/cord/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/oliverbj/cord/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/oliverbj/cord/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/oliverbj/cord.svg?style=flat-square)](https://packagist.org/packages/oliverbj/cord)

Cord offers an expressive, chainable API for interacting with CargoWise One's eAdapter over HTTP.

## Table of Contents

- [Support](#support)
- [Installation](#installation)
- [Laravel Boost](#laravel-boost)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Operation Schemas](#operation-schemas)
  - [Targets](#targets)
  - [Request context](#request-context)
- [Documents / eDocs](#documents--edocs)
  - [Upload documents](#upload-documents)
  - [Add events](#add-events)
- [Organizations](#organizations)
  - [Query Organization](#query-organization)
  - [Create Organization](#create-organization)
  - [Update Organization](#update-organization)
    - [Add an address](#add-an-address)
    - [Add a contact](#add-a-contact)
    - [Add EDI communication details](#add-edi-communication-details)
    - [Transfer existing organization data](#transfer-existing-organization-data)
- [Company](#company)
  - [Query Company](#query-company)
- [Staff](#staff)
  - [Create Staff](#create-staff)
  - [Update Staff](#update-staff)
- [One Off Quotes](#one-off-quotes)
    - [Query One-Off Quote](#query-one-off-quote)
  - [Create One-Off Quote](#create-one-off-quote)
- [Multiple Connections](#multiple-connections)
- [Raw XML](#raw-xml)
- [Response as JSON](#response-as-json)
- [Response as XML](#response-as-xml)
- [Debugging](#debugging)
- [Testing](#testing)
  - [Manual staff test](#manual-staff-test)

## Support

Cord currently targets:

- PHP `8.2+`
- Laravel `11` and `12`

## Installation

Install the package via Composer:

```bash
composer require oliverbj/cord
```

Publish the config file:

```bash
php artisan vendor:publish --tag="cord-config"
```

## Laravel Boost

If your application uses [Laravel Boost](https://laravel.com/docs/13.x/boost), Cord ships package-owned Boost resources so Boost can include Cord guidance automatically.

- Cord includes a core guideline that covers configuration, request flow, response handling, and package conventions.
- Cord also includes an optional `cord-development` skill for on-demand help with `describe()`, `schema()`, `fromStructured()`, `inspect()`, `toJson()`, `toXml()`, and `rawXml()`.

In the consuming Laravel application:

```bash
php artisan boost:install
```

After updating Cord or Boost itself, refresh generated agent resources with:

```bash
php artisan boost:update
```

Boost discovers these resources from the package automatically, so Cord does not require an extra publish step beyond Boost's normal install and update commands.

## Configuration

The published `config/cord.php` file looks like this:

```php
return [
    'base' => [
        'eadapter_connection' => [
            'url' => env('CORD_URL', ''),
            'username' => env('CORD_USERNAME', ''),
            'password' => env('CORD_PASSWORD', ''),
        ],
    ],
];
```

Set your CargoWise eAdapter credentials in `.env`:

```env
CORD_URL=
CORD_USERNAME=
CORD_PASSWORD=
```

## Usage

Start with a target, call `get()` before `run()` for organization and one-off quote retrievals, then execute the request with `run()`. By default Cord returns the decoded eAdapter payload as an array. Call `toJson()` or `toXml()` before `run()` when you need serialized output.

### Operation Schemas

Cord now exposes AI-facing operation contracts and structured execution helpers.

```php
use Oliverbj\Cord\Facades\Cord;

$schema = Cord::schema('one_off_quote.create');

$response = Cord::fromStructured('one_off_quote.create', [
    'company' => 'CPH',
    'branch' => 'A01',
    'department' => 'FES',
    'transport_mode' => 'SEA',
    'port_of_origin' => 'AUSYD',
    'port_of_destination' => 'NZAKL',
])->run();
```

`describe()` is registry-backed:

- `Cord::describe()` lists all published resources and operation ids.
- `Cord::staff()->describe()` lists the operations for the selected resource.
- `Cord::staff()->create()->describe()` returns the active JSON-Schema-style contract for that fully scoped builder.

### Targets

Cord currently supports these main targets:

- Bookings via `booking()`
- Shipments via `shipment()`
- One-off quotes via `oneOffQuote()`
- Customs declarations via `custom()`
- Organizations via `organization()`
- Companies via `company()`
- Receivables / invoices via `receivable()` or `receiveable()` for document requests

```php
use Oliverbj\Cord\Facades\Cord;

Cord::shipment('SMIA12345678')->run();

Cord::withCompany('CPH')
    ->oneOffQuote('QCPH00001004')
    ->get()
    ->run();

Cord::withCompany('CPH')
    ->oneOffQuote()
    ->create()
    ->branch('A01')
    ->department('FES')
    ->transportMode('SEA')
    ->portOfOrigin('AUSYD')
    ->portOfDestination('NZAKL')
    ->run();

Cord::custom('BATL12345678')->run();

Cord::organization('SAGFURHEL')->get()->run();

Cord::company('CPH')->run();
```

### Request context

You can scope a request to a specific CargoWise company:

```php
Cord::shipment('SMIA12345678')
    ->withCompany('CPH')
    ->run();
```

For universal requests, `withCompany()` also enables Cord to derive a `SenderID` from the configured eAdapter host plus the company code, for example `DEMO1TRNCPH`. The default `RecipientID` is `Cord`.

If you need to override either value, you can set them explicitly:

```php
Cord::shipment('SMIA12345678')
    ->withSenderId('PartnerA')
    ->withRecipientId('PartnerB')
    ->run();
```

## Documents / eDocs

Most entities in CargoWise One expose eDocs. Use `withDocuments()` to retrieve a document collection for the selected target.

```php
Cord::shipment('SMIA12345678')
    ->withDocuments()
    ->run();
```

When fetching documents you can add filters:

```php
Cord::shipment('SMIA92838292')
    ->withDocuments()
    ->filter('DocumentType', 'ARN')
    ->filter('IsPublished', true)
    ->run();
```

Available filters:

- `DocumentType` retrieves only documents matching the specified document type.
- `IsPublished` retrieves only published or unpublished documents. Valid values are `true` and `false`.
- `SaveDateUTCFrom` retrieves only documents added or modified on or after the specified UTC timestamp.
- `SaveDateUTCTo` retrieves only documents added or modified on or before the specified UTC timestamp.
- `CompanyCode` retrieves only documents related to the specified company, or non-company-specific documents.
- `BranchCode` retrieves only documents related to the specified branch.
- `DepartmentCode` retrieves only documents related to the specified department.

### Upload documents

You can upload a document to a CargoWise file with `addDocument()`:

```php
Cord::shipment('SJFK21060014')
    ->addDocument(
        file_contents: base64_encode(file_get_contents('myfile.pdf')),
        name: 'myfile.pdf',
        type: 'MSC',
        description: 'Optional description',
        isPublished: true,
    )
    ->run();
```

### Add events

Cord can also push events to jobs:

```php
Cord::shipment('SJFK21060014')
    ->addEvent(
        date: now()->toIso8601String(),
        type: 'DIM',
        reference: 'My Reference',
        isEstimate: true,
    )
    ->run();
```

## Organizations

Cord maps beautifully into the organization module of CargoWise.

### Query Organization

Use `organization()` with one or more `criteriaGroup()` calls for native organization queries, then call `get()` before `run()`.

```php
Cord::organization()
    ->criteriaGroup([
        [
            'Entity' => 'OrgHeader',
            'FieldName' => 'Code',
            'Value' => 'US%',
        ],
        [
            'Entity' => 'OrgHeader',
            'FieldName' => 'IsBroker',
            'Value' => 'True',
        ],
    ], type: 'Partial')
    ->get()
    ->run();
```

If the caller needs the organization payload as JSON instead of the default array, call `toJson()` before `run()`:

```php
$json = Cord::organization()
    ->criteriaGroup([
        [
            'Entity' => 'OrgHeader',
            'FieldName' => 'Code',
            'Value' => 'SAGFURHEL',
        ],
    ], type: 'Key')
    ->get()
    ->toJson()
    ->run();
```

The `type` argument can be either `Key` or `Partial`. `Key` is the default.

#### Partial Match Retrieval
You can retrieve entities by providing field names along with either complete values or partial 
values using wildcards to filter by. Multiple criteria items can be provided and multiple groups of 
criteria can be provided.

All items within a criteria group assume an ‘And’ operation, whilst an ‘Or’ operation is performed 
between each criteria group.


You can define multiple criteria groups. Multiple groups behave like an `OR` statement:

```php
Cord::organization()
    ->criteriaGroup([
        [
            'Entity' => 'OrgHeader',
            'FieldName' => 'Code',
            'Value' => 'US%',
        ],
    ], type: 'Partial')
    ->criteriaGroup([
        [
            'Entity' => 'OrgHeader',
            'FieldName' => 'IsBroker',
            'Value' => 'True',
        ],
    ], type: 'Partial')
    ->get()
    ->run();
```


#### Unique Key Based Retrieval

This is the retrieval of data by providing a candidate key (unique reference). This message requires that the Code property on the table OrgHeader is a candidate key to work.

If you specify a FieldName that is not a candidate key, a Rejection status will be returned with an 
appropriate error message.

When using unique key based retrieval, only a single key can be specified. There will only be one
Criteria element, as multiple criteria with different unique keys would never return a result. This is 
because you could never find an Organization with a code of ABC and XYZ


You can define multiple criteria groups. Multiple groups behave like an `OR` statement:

```php
Cord::organization()
    ->criteriaGroup([
        [
            'Entity' => 'OrgHeader',
            'FieldName' => 'Code',
            'Value' => 'ABCDEF',
        ],
    ], type: 'Key')
    ->get()
    ->run();
```



### Create Organization

New organizations are created with `organization('CODE')->create()`. Supply at least `fullName()` — all other setters are optional. Multiple addresses and contacts can be chained.

```php
Cord::withCompany('CPH')
    ->organization('NEWORG')
    ->create()
    ->fullName('New Organization Ltd')
    ->isActive(true)
    ->isForwarder(true)
    ->isConsignee(true)
    ->isConsignor(false)
    ->isAirLine(false)
    ->closestPort('AUSYD')
    ->addAddress(fn ($a) => $a
        ->code('MAIN ST')
        ->addressOne('1 Main Street')
        ->country('AU')
        ->city('Sydney')
        ->capability('OFC', isMainAddress: true)
    )
    ->addContact(fn ($c) => $c
        ->name('Operations')
        ->email('ops@example.com')
    )
    ->run();
```

| Method | Required | Description |
|---|---|---|
| `fullName(string)` | ✅ | Organisation display name |
| `isActive(bool)` | | Defaults to `true` |
| `isConsignee(bool)` | | Organisation role flag |
| `isConsignor(bool)` | | Organisation role flag |
| `isForwarder(bool)` | | Organisation role flag |
| `isAirLine(bool)` | | Organisation role flag |
| `closestPort(string)` | | UNLOCO code for closest port |
| `addAddress(Closure)` | | Repeatable; see address builder below |
| `addContact(Closure)` | | Repeatable; see contact builder below |

> **Note:** `addAddress()` and `addContact()` use the same builders as the update path. See [Add an address](#add-an-address) and [Add a contact](#add-a-contact).

### Update Organization

Native organization updates are anchored on `organization('CODE')->update()`. Call `update()` before any write setter — this mirrors the `staff()->update()` pattern.

#### Add an address

```php
Cord::withCompany('CPH')
    ->organization('SAGFURHEL')
    ->update()
    ->addAddress(fn ($a) => $a
        ->code('MAIN STREET NO. 1')
        ->addressOne('Main Street')
        ->addressTwo('Number One')
        ->country('US')
        ->city('Anytown')
        ->state('NY')
        ->postcode('12345')
        ->relatedPort('USNYC')
        ->capability('OFC', isMainAddress: false)
    )
    ->run();
```

Required: `code`, `addressOne`, `country`, `city`.  
Use `->capability($addressType, $isMainAddress)` to append an address type. Call it multiple times for multiple capabilities.

Available setters: `code`, `addressOne`, `addressTwo`, `country`, `city`, `state`, `postcode`, `relatedPort`, `phone`, `fax`, `mobile`, `email`, `dropModeFCL`, `dropModeLCL`, `dropModeAIR`, `active`, `capability`.

#### Add a contact

```php
Cord::withCompany('CPH')
    ->organization('SAGFURHEL')
    ->update()
    ->addContact(fn ($c) => $c
        ->name('Jane Doe')
        ->email('jane@example.com')
        ->phone('+1 555 123 4567')
        ->language('EN')
    )
    ->run();
```

Required: `name`, `email`.  
Available setters: `name`, `email`, `active`, `notifyMode`, `title`, `gender`, `language`, `phone`, `mobilePhone`, `homePhone`, `attachmentType`.

#### Add EDI communication details

```php
Cord::withCompany('CPH')
    ->organization('SAGFURHEL')
    ->update()
    ->addEDICommunication(fn ($e) => $e
        ->module('IMP')
        ->purpose('CUS')
        ->direction('OUT')
        ->transport('EML')
        ->destination('ops@example.com')
        ->format('XML')
    )
    ->run();
```

Required: `module`, `purpose`, `direction`, `transport`, `destination`, `format`.  
Optional setters: `subject`, `publishMilestones`, `senderVAN`, `receiverVAN`, `filename`.

#### Transfer existing organization data

The transfer helpers copy an existing entity from a source organization payload to a target organization. They still accept a raw array sourced directly from a CargoWise payload:

- `transferAddress()`
- `transferContact()`
- `transferEDICommunication()`
- `transferDocumentTracking()`

```php
$source = Cord::organization('SOURCE')->get()->run();

Cord::withCompany('CPH')
    ->organization('TARGET')
    ->update()
    ->transferContact($source['OrgContactCollection']['OrgContact'][0])
    ->run();
```


#### Schema and structured execution

All organization write operations are registered in the operation registry, so `fromStructured()` and `schema()` work the same way as for One-Off Quotes and Staff:

```php
// Introspect a specific operation
$schema = Cord::schema('organization.address.add');

// Execute via structured payload
$response = Cord::fromStructured('organization.contact.add', [
    'company' => 'CPH',
    'code' => 'SAGFURHEL',
    'contact' => [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ],
])->run();
```

## Company
You are able to use Cord to interact with companies in CargoWise.

### Query Company

Company queries follow the same native query pattern:

```php
Cord::company()
    ->criteriaGroup([
        [
            'Entity' => 'GlbCompany',
            'FieldName' => 'Code',
            'Value' => 'CPH',
        ],
    ])
    ->run();
```
## Staff
You can also use Cord to manage Staff records in CargoWise.

### Create Staff

Staff creation is sent as a native `Native` request. Company context is required. `EnterpriseID` and `ServerID` are derived from the configured `url`, and `CodesMappedToTarget` defaults to `true`. You can override the native context with `withEnterprise()`, `withServer()`, or `withCodeMapping(false)`.

```php
Cord::withCompany('CPH')
    ->staff()
    ->create()
    ->code('BVO')
    ->loginName('user.test')
    ->password('1234')
    ->fullName('User Test')
    ->email('user.test@test.com')
    ->branch('TLS')
    ->department('FES')
    ->phone('+111')
    ->isActive(true)
    ->country('FR')
    ->replaceGroups(['ORGALL', 'OPSALL'])
    ->withPayload([
        'FriendlyName' => 'User Test',
        'Title' => 'Operations Specialist',
        'GlbWorkTime' => [
            '_attributes' => ['Action' => 'Insert'],
            'MondayWorkingHours' => '*******************',
            'TuesdayWorkingHours' => '*******************',
        ],
    ])
    ->run();
```

Common fluent setters:

- `code`, `loginName`, `password`, `fullName`, `branch`, `department`, and `country` are required for create requests.
- `withCompany('CPH')` is required for native staff create/update requests.
- `password(...)` automatically sets `ChangePasswordAtNextLogin` to `true`.
- new staff create payloads automatically include `IsOperational=true`.
- `replaceGroups([...])`, `addGroup(...)`, and `removeGroup(...)` are available for explicit group semantics.
- `withPayload([...])` can be used as a passthrough for CargoWise fields not yet wrapped by dedicated methods.
- `toPayload()` returns the compiled staff payload without sending a request.

Method introspection:

```php
$schema = Cord::schema('staff.create');
$operations = Cord::staff()->describe();
```

`schema()` returns a JSON-Schema-style contract with `properties`, `required`, nested `items`, enums, and `x-cord` metadata for the operation id, resource, and action.

### Update Staff

Staff updates are sent as native `Native` requests with `Action="UPDATE"`.

```php
Cord::withCompany('CPH')
    ->staff('BVO')
    ->update()
    ->fullName('Updated User')
    ->email('updated@example.com')
    ->branch('CPH')
    ->department('OPS')
    ->removeGroup('OLDOPS')
    ->addGroup('NEWOPS')
    ->phone('+4511223344')
    ->country('DK')
    ->withPayload([
        'FriendlyName' => 'Updated',
        'Title' => 'Branch Manager',
        'GlbWorkTime' => [
            '_attributes' => ['Action' => 'Update'],
            'MondayWorkingHours' => '********',
        ],
    ])
    ->run();
```

## One Off Quotes

Use Cord to interact with the One-Off Quote module in CargoWise.

### Query One-Off Quote

One-off quote retrieval uses the universal shipment request with `DataTarget Type="OneOffQuote"` and a quote key.

Call `withCompany()` before `run()` so Cord can populate the One-Off Quote `DataContext` with the company, `EnterpriseID`, `ServerID`, and the `ORP` recipient role expected by CargoWise.

```php
$response = Cord::withCompany('CPH')
    ->oneOffQuote('QCPH00001004')
    ->get()
    ->run();
```

One-off quote query introspection:

```php
$schema = Cord::schema('one_off_quote.get');
$active = Cord::oneOffQuote('QCPH00001004')->get()->describe();
```

### Create One-Off Quote

One-off quote creation is sent as a universal shipment request with `DataTarget Type="OneOffQuote"`.

```php
Cord::withCompany('CPH')
    ->oneOffQuote()
    ->create()
    ->branch('A01')
    ->department('FES')
    ->transportMode('SEA')
    ->portOfOrigin('AUSYD')
    ->portOfDestination('NZAKL')
    ->serviceLevel('STD')
    ->incoterm('DAP')
    ->totalWeight(5000, 'KG')
    ->totalVolume(19.2, 'M3')
    ->goodsValue(15000, 'AUD')
    ->additionalTerms('Export Only')
    ->isDomesticFreight(false)
    ->clientAddress(fn ($a) => $a
        ->addressLine1('3 TENTH AVENUE')
        ->city('OYSTER BAY')
        ->country('AU')
    )
    ->pickupAddress(fn ($a) => $a
        ->addressLine1('3 TENTH AVENUE')
        ->city('OYSTER BAY')
        ->country('AU')
    )
    ->deliveryAddress(fn ($a) => $a
        ->addressLine1('10 TEST ROAD')
        ->city('AUCKLAND')
        ->country('NZ')
    )
    ->addChargeLine(fn ($c) => $c
        ->chargeCode('FRT')
        ->description('International Freight')
        ->costAmount('500.0000', 'AUD')
        ->sellAmount('1500.0000', 'AUD')
    )
    ->addAttachedDocument(fn ($d) => $d
        ->fileName('Quote.pdf')
        ->imageData(base64_encode(file_get_contents('Quote.pdf')))
        ->type('QTE')
        ->isPublished(true)
    )
    ->withPayload([
        'CustomizedFieldCollection' => [
            'CustomizedField' => [
                'DataType' => 'String',
                'Key' => 'Test User',
                'Value' => 'Janice Testing',
            ],
        ],
    ])
    ->run();
```

One-off quote create requirements:

- `withCompany(...)`
- `branch(...)`
- `department(...)`
- `transportMode(...)`
- `portOfOrigin(...)`
- `portOfDestination(...)`

One-off quote introspection:

```php
$querySchema = Cord::schema('one_off_quote.get');
$query = Cord::oneOffQuote('QCPH00001004')->get()->describe();

$schema = Cord::schema('one_off_quote.create');
$active = Cord::oneOffQuote()->create()->describe();
```

## Multiple Connections

If you need to connect to multiple eAdapters, use `withConfig()`:

```php
Cord::shipment('SJFK21060014')
    ->withConfig('archive')
    ->run();
```

Then add the additional connection to `config/cord.php`:

```php
return [
    'base' => [
        'eadapter_connection' => [
            'url' => env('CORD_URL', ''),
            'username' => env('CORD_USERNAME', ''),
            'password' => env('CORD_PASSWORD', ''),
        ],
    ],

    'archive' => [
        'eadapter_connection' => [
            'url' => env('CORD_ARCHIVE_URL', ''),
            'username' => env('CORD_ARCHIVE_USERNAME', ''),
            'password' => env('CORD_ARCHIVE_PASSWORD', ''),
        ],
    ],
];
```

The configured URL does not have to point directly at the eAdapter itself. It can point to middleware, as long as that middleware forwards the request and returns the eAdapter response. If you want enterprise and server auto-detection for native write requests, the URL should preserve the CargoWise host pattern such as `https://demo1trnservices.example.invalid/eAdaptor`.

## Raw XML

If you already have a complete XML payload and just want Cord to send it with the configured eAdapter credentials and headers, use `rawXml()`:

```php
$response = Cord::withConfig('NTG_TRN')
    ->rawXml($xml)
    ->run();
```

For raw XML requests, `run()` returns the full parsed eAdapter envelope instead of only `Data`. That means `Status`, `ProcessingLog`, and `Data` are all preserved.

```php
$status = $response['Status'];
$message = $response['ProcessingLog'] ?? null;
```

`inspect()` still performs a dry run and returns the outbound XML unchanged:

```php
$xml = Cord::rawXml($xml)->inspect();
```

## Response as JSON

If you want the same response payload serialized as JSON, call `toJson()` before `run()`:

```php
$json = Cord::shipment('SJFK21041242')
    ->toJson()
    ->run();
```

For `rawXml()` requests, `toJson()` returns the full response envelope as JSON, including `Status`, `ProcessingLog`, and `Data`:

```php
$json = Cord::rawXml($xml)
    ->toJson()
    ->run();
```

## Response as XML

If you want the original eAdapter response as XML, call `toXml()` before `run()`:

```php
Cord::shipment('SJFK21041242')
    ->toXml()
    ->run();
```

For `rawXml()` requests, `toXml()` returns the full response envelope, including `Status` and `ProcessingLog`:

```php
$response = Cord::rawXml($xml)
    ->toXml()
    ->run();
```

## Debugging

Use `inspect()` to build and inspect the outgoing XML without sending the HTTP request:

```php
$xml = Cord::custom('BJFK21041242')
    ->withDocuments()
    ->filter('DocumentType', 'ARN')
    ->inspect();

return response($xml, 200, ['Content-Type' => 'application/xml']);
```

## Testing

```bash
composer test
```

`composer test` now covers both staff creation and non-collection staff updates.

### Manual staff test

For controlled manual testing, Cord includes a local runner script that builds the staff payload and only sends it when you explicitly opt in. By default it creates staff; add `--update` to send an update instead.

1. Create a local `.env` from `.env.example` and fill in your connection details.
2. Copy `resources/manual/staff-payload.example.php` to `resources/manual/staff-payload.local.php`.
3. Edit the local payload file with the staff data you want to test.
4. Run an inspect-only dry run first:

```bash
composer manual-staff-test -- --connection=NTG_TRN --company=CPH
```

That prints the exact XML and does not send anything.

When you are ready to post the request, run:

```bash
composer manual-staff-test -- --connection=NTG_TRN --company=CPH --send
```

To update an existing staff row such as `XX0`, keep the `code` in your payload and add `--update`:

```bash
composer manual-staff-test -- --connection=NTG_TRN --payload=resources/manual/staff-payload.local.php --update
composer manual-staff-test -- --connection=NTG_TRN --payload=resources/manual/staff-payload.local.php --update --send
```

Example local `.env`:

```env
CORD_URL=https://demo1trnservices.example.invalid/eAdaptor
CORD_USERNAME=your-eadapter-user
CORD_PASSWORD=xxxx
```

The runner derives `EnterpriseID=XXX` and `ServerID=TRN` from that URL and uses them in the native `DataContext`.

You can still override the derived native context per run if needed:

```bash
composer manual-staff-test -- \
  --connection=MY_TRN \
  --company=CPH \
  --enterprise=XXX \
  --server=TRN
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/oliverbj/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Oliver Busk](https://github.com/oliverbj)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
