# TNTSearch Driver for Laravel Scout - Laravel 5.3 [WIP]

[![Latest Version on Packagist](https://img.shields.io/packagist/v/teamtnt/laravel-scout-tntsearch-driver.svg?style=flat-square)](https://packagist.org/packages/teamtnt/laravel-scout-tntsearch-driver)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/teamtnt/laravel-scout-tntsearch-driver/master.svg?style=flat-square)](https://travis-ci.org/teamtnt/laravel-scout-tntsearch-driver)
[![StyleCI](https://styleci.io/repos/65626858/shield)](https://styleci.io/repos/65626858)
[![Quality Score](https://img.shields.io/scrutinizer/g/teamtnt/laravel-scout-tntsearch-driver.svg?style=flat-square)](https://scrutinizer-ci.com/g/teamtnt/laravel-scout-tntsearch-driver)
[![Total Downloads](https://img.shields.io/packagist/dt/teamtnt/laravel-scout-tntsearch-driver.svg?style=flat-square)](https://packagist.org/packages/teamtnt/laravel-scout-tntsearch-driver)

This package makes it easy to add full text search support to your models with Laravel 5.3.

## Contents

- [Installation](#installation)
    - [Setting up the TNTSearch Scout Driver](#setting-up-tntsearch-scout-driver)
- [Usage](#usage)
    - [Bla bla bla](#bla-bla-bla)
- [Changelog](#changelog)
- [Testing](#testing)
- [Security](#security)
- [Contributing](#contributing)
- [Credits](#credits)
- [License](#license)


## Installation

You can install the package via composer:

``` bash
composer require teamtnt/laravel-scout-tntsearch-driver
```

You must install the service provider:

```php
// config/app.php
'providers' => [
    // ...
    TeamTNT\Scout\TNTSearchScoutServiceProvider::class,
],
```
To your `.env` file add `SCOUT_DRIVER=tntsearch`

In your `config/scout.php` add:

```php
'tntsearch' => [
    'driver'   => env('DB_CONNECTION', 'mysql'),
    'host'     => env('DB_HOST', 'localhost'),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'storage'  => storage_path(),
],
```

## Usage

After you have installed scout and the TNTSearch driver, you need to add the
`Searchable` trait to your models that you want to make searchable

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use Searchable;
}
```

Then, sync the data with the serach service like:

`php artisan scout:import App\\Post`

After that you can search your models with:

`Post::search('Bugs Bunny')->get();`

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Nenad Ticaric](https://github.com/nticaric)
- [Sasa Tokic](https://github.com/stokic)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.