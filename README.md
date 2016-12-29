# TNTSearch Driver for Laravel Scout - Laravel 5.3

[![Latest Version on Packagist](https://img.shields.io/packagist/v/teamtnt/laravel-scout-tntsearch-driver.svg?style=flat-square)](https://packagist.org/packages/teamtnt/laravel-scout-tntsearch-driver)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/teamtnt/laravel-scout-tntsearch-driver/master.svg?style=flat-square)](https://travis-ci.org/teamtnt/laravel-scout-tntsearch-driver)
[![Quality Score](https://img.shields.io/scrutinizer/g/teamtnt/laravel-scout-tntsearch-driver.svg?style=flat-square)](https://scrutinizer-ci.com/g/teamtnt/laravel-scout-tntsearch-driver)
[![Total Downloads](https://img.shields.io/packagist/dt/teamtnt/laravel-scout-tntsearch-driver.svg?style=flat-square)](https://packagist.org/packages/teamtnt/laravel-scout-tntsearch-driver)

This package makes it easy to add full text search support to your models with Laravel 5.3.

## Contents

- [Installation](#installation)
- [Usage](#usage)
- [Contributing](#contributing)
- [Credits](#credits)
- [License](#license)


## Installation

You can install the package via composer:

``` bash
composer require teamtnt/laravel-scout-tntsearch-driver
```

Add the service provider:

```php
// config/app.php
'providers' => [
    // ...
    TeamTNT\Scout\TNTSearchScoutServiceProvider::class,
],
```
Add  `SCOUT_DRIVER=tntsearch` to your `.env` file

In your `config/scout.php` add:

```php

'tntsearch' => [
    'storage'  => storage_path(), //place where the index files will be stored
    'fuzziness' => env('TNTSEARCH_FUZZINESS', false),
    'fuzzy' => [
        'prefix_length' => 2,
        'max_expansions' => 50,
        'distance' => 2
    ],
    'asYouType' => false,
],
```

## Usage

After you have installed scout and the TNTSearch driver, you need to add the
`Searchable` trait to your models that you want to make searchable. Additionaly,
define the fields you want to make searchable by defining the `toSearchableArray` method on the model:

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use Searchable;
    
    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $array = $this->toArray();

        // Customize array...

        return $array;
    }
}
```

Then, sync the data with the search service like:

`php artisan scout:import App\\Post`

If you have a lot of records and want to speed it up you can run:

`php artisan tntsearch:import App\\Post`

After that you can search your models with:

`Post::search('Bugs Bunny')->get();`

##Support [![OpenCollective](https://opencollective.com/tntsearch/backers/badge.svg)](#backers) [![OpenCollective](https://opencollective.com/tntsearch/sponsors/badge.svg)](#sponsors)

###Backers

Support us with a monthly donation and help us continue our activities. [[Become a backer](https://opencollective.com/tntsearch#backer)]

## Sponsors

Become a sponsor and get your logo on our README on Github with a link to your site. [[Become a sponsor](https://opencollective.com/tntsearch#sponsor)]

## Credits

- [Nenad Ticaric](https://github.com/nticaric)
- [Sasa Tokic](https://github.com/stokic)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
