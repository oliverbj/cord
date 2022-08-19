
[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/support-ukraine.svg?t=1" />](https://supportukrainenow.org)

# Seamless integration to CargoWise One's eAdapter using the HTTP service!

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oliverbj/cord.svg?style=flat-square)](https://packagist.org/packages/oliverbj/cord)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/oliverbj/cord/run-tests?label=tests)](https://github.com/oliverbj/cord/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/oliverbj/cord/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/oliverbj/cord/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/oliverbj/cord.svg?style=flat-square)](https://packagist.org/packages/oliverbj/cord)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/cord.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/cord)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require oliverbj/cord
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="cord-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="cord-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="cord-views"
```

## Usage

### Shipments
```php
//Get a shipment
Cord::shipment()
    ->find('SMIA12345678');
```

### Customs
```php
//Get a brokerage job
Cord::custom()
    ->find('BATL12345678');
```

### eDocs
```php
//Get all the available documents from a shipment file
Cord::documents()
    ->shipment()
    ->find('SMIA12345678');
```
When interacting with the eDocs of CargoWise One, we can provide filters to the request:
```php
//Get only documents from a shipment file that is the type "ARN"
Cord::documents()
    ->shipment()
    ->find('SMIA92838292')
    ->filter('DocumentType', 'ARN');
```

The available filters are:
```
 - **DocumentType** – Retrieve only documents matching the specified document type.
 - **IsPublished** – Retrieve only published or un-published documents. The values for this filter are: `True` and `False`. This can only be specified once.
 - **SaveDateUTCFrom** – Retrieve only documents that were added or modified on or after the specified date/time (provided in UTC time). This can only be specified once.
 - **SaveDateUTCTo** – Retrieve only documents that were added or modified on or before the specified date/time (provided in UTC time). This can only be specified once.
 - **CompanyCode** – Retrieve only documents related to the specified company or non-company specific. The default behavior without this Type being filtered is to return all documents regardless of company affiliation.
 - **BranchCode** – Retrieve only documents related to the specified branch code.
 - **DepartmentCode** – Retrieve only documents relevant to specified department code.
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
