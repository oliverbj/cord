# Seamless integration to CargoWise One's eAdapter using the HTTP service!

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oliverbj/cord.svg?style=flat-square)](https://packagist.org/packages/oliverbj/cord)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/oliverbj/cord/run-tests?label=tests)](https://github.com/oliverbj/cord/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/oliverbj/cord/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/oliverbj/cord/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/oliverbj/cord.svg?style=flat-square)](https://packagist.org/packages/oliverbj/cord)

Cord offers a expressive, chainable and easy API to interact with CargoWise One's eAdapter using their HTTP Webservice.

## Installation

You can install the package via composer:

```bash
composer require oliverbj/cord
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="cord-config"
```

This is the contents of the published config file:

```php
return [
    'eadapter_connection' => [
        'url' => env('CW1_EADAPTER_URL', ''),
        'username' => env('CW1_EADAPTER_USERNAME', ''),
        'password' => env('CW1_EADAPTER_PASSWORD', ''),
    ],
];
```

## Usage

### Setting environment
In order to use Cord, you must specify the appropiate login details for your CargoWise One eAdapter service:

```env
CW1_EADAPTER_URL=
CW1_EADAPTER_USERNAME=
CW1_EADAPTER_PASSWORD=
```

### Modules

Cord comes with connectivity to the following modules:
 - Bookings `booking()`
 - Shipments `shipment()`
 - Customs `custom()`

Similar for all, you must call the `run()` method to 'get' the actual response back from the eAdapter. The response from the eAdapter will be returned in a JSON format.

```php
//Get a shipment
Cord::shipment('SMIA12345678')
    ->run();

//Get a brokerage job
Cord::custom('BATL12345678')
    ->run();
```

### Documents / eDocs
Most entities in CargoWise One have a document tab (called eDocs). It is possible to use Cord to access these documents using the `withDocuments()` method.
When applying the documents method, Cord will only a `DcoumentCollection` containing the documents from the specified entity.

```php
//Get all the available documents from a shipment file
Cord::shipment('SMIA12345678')
    ->withDocuments()
    ->run();
```
When interacting with the eDocs of CargoWise One (getting documents), we can provide filters to the request:

```php
//Get only documents from a shipment file that is the type "ARN"
Cord::shipment('SMIA92838292')
    ->withDocuments()
    ->filter('DocumentType', 'ARN')
    ->filter('IsPublished', True)
    ->run();
```

The available filters are:
 - **DocumentType** – Retrieve only documents matching the specified document type.
 - **IsPublished** – Retrieve only published or un-published documents. The values for this filter are: `True` and `False`. This can only be specified once.
 - **SaveDateUTCFrom** – Retrieve only documents that were added or modified on or after the specified date/time (provided in UTC time). This can only be specified once.
 - **SaveDateUTCTo** – Retrieve only documents that were added or modified on or before the specified date/time (provided in UTC time). This can only be specified once.
 - **CompanyCode** – Retrieve only documents related to the specified company or non-company specific. The default behavior without this Type being filtered is to return all documents regardless of company affiliation.
 - **BranchCode** – Retrieve only documents related to the specified branch code.
 - **DepartmentCode** – Retrieve only documents relevant to specified department code.

Similar, it is also possible to upload documents to a file in CargoWise One using `addDocument`:

```php
Cord::shipment('SJFK21060014')
        ->addDocument(
            file_contents: base64_decode(file_get_contents("myfile.pdf")),
            name: 'myfile.pdf',
            type: 'MSC'
            description: '(Optional)',
            isPublished: true //default is *false*
        )
        ->run();
```

Cord also supports interacting with CargoWise One's event engine, meaning we can add events to jobs:

```php
Cord::shipment('SJFK21060014')
        ->addEvent(
            date: date('c'),
            type: 'DIM',
            reference: 'My Reference',
            isEstimate: true //default is *false*
        )
        ->run();
```

### Multiple Connections

Sometimes you may want to connect to multiple eAdapters. This can be done by using the `withConfig` method:

```php
$config = "my_custom_connection";
Cord::shipment('SJFK21060014')
      ->withConfig($config)
      ->run();
```

Then you can add the connection details to your `config/cord.php` file:

```php
return [
    'base' => [
            'eadapter_connection' => [
            'url' => env('CW1_EADAPTER_URL', ''),
            'username' => env('CW1_EADAPTER_USERNAME', ''),
            'password' => env('CW1_EADAPTER_PASSWORD', ''),
        ],
    ]

    'my_custom_connection' => [
        'eadapter_connection' => [
            'url' => env('ANOTHER_CW1_EADAPTER_URL', ''),
            'username' => env('ANOTHER_CW1_EADAPTER_USERNAME', ''),
            'password' => env('ANOTHER_CW1_EADAPTER_PASSWORD', ''),
        ],
    ],
];
```

```php

### Response as XML
If you want to return the original eAdaptor response directly as XML, call `toXml()` before you call the `run()` method:

```php
Cord::shipment('SJFK21041242')->toXml()->run();
```

### Debugging
Sometimes you may want to inspect the XML request before it's sent to the eAdapter. To do this, you can simply call the `inspect()` method. This will return the XML string repesentation:

```php
$xml = Cord::custom('BJFK21041242')
            ->documents()
            ->filter('DocumentType', 'ARN')
            ->inspect();

return response($xml, 200, ['Content-Type' => 'application/xml']);
```



## Testing

```bash
composer test
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
