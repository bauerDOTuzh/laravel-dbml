
# CURRENTLY PUBLISHED AS TEST PACKAGE!

# Laravel DBML

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/bauerdot/laravel-dbml.svg?style=flat-square)](https://packagist.org/packages/bauerdot/laravel-dbml)

## Install
`composer require bauerdot/laravel-dbml`

## Usage
- For listing all tables in database: `php artisan dbml:list` (custom type --custom)
- For Parse from DB to DBML: `php artisan dbml:parse` (see options below)

### Available Options

```
--dbdocs           Generate output for DBDocs
--custom           Use custom type mapping
--include-system   Include Laravel system tables (migrations, cache, etc.)
--no-ignore        Don't ignore any tables, include everything
--ignore-preset=   Specify ignore presets to use (system,spatie-permissions,telescope)
--config=          Path to external configuration file
--only=          Only parse specific tables (comma-separated list or multiple values)
```

## Table Filtering and Ignore Presets

### System Tables

By default, Laravel system tables are ignored. You can include them with the `--include-system` flag:

```bash
php artisan dbml:parse --include-system
```

### Ignore Presets

The package comes with predefined groups of tables (presets) that can be ignored:

- **system**: Laravel's core tables (migrations, jobs, cache, etc.)
- **spatie-permissions**: Tables from the Spatie Laravel-Permission package
- **telescope**: Laravel Telescope tables

You can specify which presets to use:

```bash
# Use only system and telescope presets
php artisan dbml:parse --ignore-preset=system,telescope

# Use only spatie-permissions preset
php artisan dbml:parse --ignore-preset=spatie-permissions
```

### Include All Tables

If you want to include all tables with no filtering, use the `--no-ignore` flag:

```bash
php artisan dbml:parse --no-ignore
```

## Configuration File

You can specify a custom configuration file with the `--config` option:

```bash
php artisan dbml:parse --config=path/to/config.json
```

The config file can be in JSON, PHP, or YAML format and supports the following options:

```json
{
    "system_tables": ["migrations", "failed_jobs", "password_resets"],
    "ignored_tables": ["audit_logs", "log_*"],
    "document_casts": true,
    "document_cast_types": ["json", "array", "object", "collection"],
    "document_spatie_data": true,
    "inline_schema": true,
    "spatie_data_namespace": "App\\ValueObjects",
    "only_tables": ["users", "brokers", "client_*"],
    "models_dir": "app/Models"
}
```

### Available Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `ignore_by_default` | boolean | true | Whether to ignore tables by default |
| `ignore_presets` | array | system, spatie-permissions, telescope | Predefined groups of tables to ignore |
| `active_presets` | array | ['system'] | Presets that are active by default |
| `ignored_tables` | array | [] | Additional tables to always ignore |
| `document_casts` | boolean | true | Whether to document Laravel cast attributes |
| `document_cast_types` | array | json, array, etc. | Which cast types to document |
| `document_spatie_data` | boolean | true | Whether to document Spatie Data objects |
| `inline_schema` | boolean | true | Put schema in column-level notes instead of table notes |
| `spatie_data_namespace` | string | App\\ValueObjects | Namespace for your Spatie Data objects |
| `only_tables` | array | [] | Only process these tables (supports wildcards) |
| `models_dir` | string | app/Models | Directory where your Laravel models are located |

### Publishing the Configuration

You can publish the package configuration file with:

```bash
php artisan vendor:publish --provider="Bauerdot\\LaravelDbml\\LaravelDbmlServiceProvider" --tag="config"
```

## Parsing Specific Tables Only

You can use the `--only` option to specify which tables should be included in the DBML output:

```bash
# Parse only the 'users' and 'posts' tables
php artisan dbml:parse --only=users,posts

# Parse tables that match a pattern
php artisan dbml:parse --only="user_*"

# You can also specify multiple --only options
php artisan dbml:parse --only=users --only=posts
```

## Column-Level Schema Documentation

This package adds schema information directly to column definitions, making it easier to understand complex data structures:

```
Table brokers {
    id int [pk, increment]
    settings json [null, note: 'Schema: {
      "theme": "string",
      "notifications": {
        "email": "boolean",
        "sms": "boolean"
      }
    }']
    // other columns...
}
```

## Laravel Model Cast Documentation

This package can automatically detect Laravel model cast attributes and document their structure in the DBML output. This is especially useful for JSON columns where the structure is defined in the model.

To document the JSON structure in your models, add a `@json-structure` tag to your property or accessor method:

```php
/**
 * @json-structure {
 *   "name": "string",
 *   "address": {
 *     "street": "string",
 *     "city": "string"
 *   }
 * }
 */
protected $casts = [
    'settings' => 'json',
];
```

## Spatie Data Object Support

The package can analyze Spatie Data objects used in Laravel model casts and document their structure. For example, with a model like this:

```php
class Broker extends Model
{
    protected $casts = [
        'permanent_address' => AddressValueObject::class,
        'contact_address' => AddressValueObject::class
    ];
}
```

And a Spatie Data object like this:

```php
class AddressValueObject extends Data
{
    public function __construct(
        public readonly ?string $city,
        public readonly ?string $street,
        public readonly ?string $country,
        public readonly ?string $zip,
        public readonly ?string $houseNumber,
        public readonly ?string $district = null,
        public readonly ?string $referenceNumber = null
    ) {}
}
```

The DBML output will include the structure:

```
permanent_address json [null, note: 'Schema: Spatie Data Object (AddressValueObject): {
  city: ?string (nullable)
  street: ?string (nullable)
  country: ?string (nullable)
  zip: ?string (nullable)
  houseNumber: ?string (nullable)
  district: ?string (nullable) = null
  referenceNumber: ?string (nullable) = null
}']
```

## Customizable Type
- Store file in /storage/app/custom_type.json
- Example
  - { "type": "target_type" }

## Credits

- [Arsanandha Aphisitworachorch](https://github.com/aphisitworachorch) - Original author
- [bauerdot](https://github.com/bauerdotuzh) - Fork maintainer
- [All Contributors](https://github.com/bauerdotuzh/laravel-dbml/contributors)

## Security
If you discover any security-related issues, please open an issue or pull request.

## License
The MIT License (MIT). Please see [License File](/LICENSE.md) for more information.


## For developers
- you need to use the package in you project for developemtn you need already existing project
and then add this to composer .json
```json
 "repositories": [
        {
            "type": "path",
            "url": "../laravel-dbml",
            "options": {
                "symlink": true
            }
        }
    ]
``` 

and then run 
```bash
composer require bauerdot/laravel-dbml:@dev
```

- since this package has no laravel instalation you need therefore to have it
+ no need for autodiscovery this package has it already inside
