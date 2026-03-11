# Seamless integration to CargoWise One's eAdapter using Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oliverbj/cord.svg?style=flat-square)](https://packagist.org/packages/oliverbj/cord)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/oliverbj/cord/run-tests?label=tests)](https://github.com/oliverbj/cord/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/oliverbj/cord/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/oliverbj/cord/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/oliverbj/cord.svg?style=flat-square)](https://packagist.org/packages/oliverbj/cord)

Cord offers an expressive, chainable API for interacting with CargoWise One's eAdapter over HTTP.

## Table of Contents

- [Support](#support)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Documents / eDocs](#documents--edocs)
- [Native](#native)
- [Multiple Connections](#multiple-connections)
- [Debugging](#debugging)
- [Testing](#testing)

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

Start with a target, then call `run()` to execute the request. By default Cord returns the decoded eAdapter payload as an array.

### Targets

Cord currently supports these main targets:

- Bookings via `booking()`
- Shipments via `shipment()`
- Customs declarations via `custom()`
- Organizations via `organization()`
- Companies via `company()`
- Receivables / invoices via `receivable()` or `receiveable()` for document requests

```php
use Oliverbj\Cord\Facades\Cord;

Cord::shipment('SMIA12345678')->run();

Cord::custom('BATL12345678')->run();

Cord::organization('SAGFURHEL')->run();

Cord::company('CPH')->run();
```

### Request context

You can scope a request to a specific CargoWise company:

```php
Cord::shipment('SMIA12345678')
    ->withCompany('CPH')
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

## Native

Cord groups native functionality into query and update flows.

### Query Organization

Use `organization()` with one or more `criteriaGroup()` calls for native organization queries.

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
    ->run();
```

The `type` argument can be either `Key` or `Partial`. `Key` is the default.

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
    ->run();
```


### Update Organization

Native organization updates are anchored on `organization('CODE')`. 

#### Add an address

```php
Cord::::organization('SAGFURHEL')
    ->addAddress([
        'code' => 'MAIN STREET NO. 1',
        'addressOne' => 'Main Street',
        'addressTwo' => 'Number One',
        'country' => 'US',
        'city' => 'Anytown',
        'state' => 'NY',
        'postcode' => '12345',
        'relatedPort' => 'USNYC',
        'capabilities' => [
            'AddressType' => 'OFC',
            'IsMainAddress' => 'false',
        ],
    ])
    ->run();
```

#### Add a contact

```php
Cord::organization('SAGFURHEL')
    ->addContact([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+1 555 123 4567',
        'language' => 'EN',
    ])
    ->run();
```

You can also include optional `documentsToDeliver` data when creating the contact.

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

#### Add EDI communication details

```php
Cord::organization('SAGFURHEL')
    ->addEDICommunication([
        'module' => 'IMP',
        'purpose' => 'CUS',
        'direction' => 'OUT',
        'transport' => 'EML',
        'destination' => 'ops@example.com',
        'format' => 'XML',
    ])
    ->run();
```

#### Transfer existing organization data

The transfer helpers are useful when you already have data from an organization payload and want to copy it to another organization:

- `transferAddress()`
- `transferContact()`
- `transferEDICommunication()`
- `transferDocumentTracking()`

```php
$source = Cord::organization('SOURCE')->run();

Cord::withCompany('CPH')
    ->organization('TARGET')
    ->transferContact($source['OrgContactCollection']['OrgContact'][0])
    ->run();
```

### Add Staff

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
- `replaceGroups([...])` and `addGroup(...)` are available for explicit group semantics.
- `withPayload([...])` can be used as a passthrough for CargoWise fields not yet wrapped by dedicated methods.
- `toPayload()` returns the compiled staff payload without sending a request.

Method introspection:

```php
$schema = Cord::staff()->describe();
```

`describe()` returns structured metadata about the staff API surface:

- resource name
- supported actions
- available fluent methods with parameter types
- required intent context (`required_for`)
- method descriptions and examples

### Edit Staff

Staff updates are sent as native `Native` requests with `Action="UPDATE"`.

```php
Cord::withCompany('CPH')
    ->staff('BVO')
    ->update()
    ->fullName('Updated User')
    ->email('updated@example.com')
    ->branch('CPH')
    ->department('OPS')
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

## Response as XML

If you want the original eAdapter response as XML, call `toXml()` before `run()`:

```php
Cord::shipment('SJFK21041242')
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
