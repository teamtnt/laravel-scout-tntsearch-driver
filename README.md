# TNTSearch Driver for Laravel Scout - Laravel 5.3 - 13

[![Backers on Open Collective](https://opencollective.com/laravel-scout-tntsearch-driver/backers/badge.svg)](#backers) [![Sponsors on Open Collective](https://opencollective.com/laravel-scout-tntsearch-driver/sponsors/badge.svg)](#sponsors) [![Latest Version on Packagist](https://img.shields.io/packagist/v/teamtnt/laravel-scout-tntsearch-driver.svg?style=flat-square)](https://packagist.org/packages/teamtnt/laravel-scout-tntsearch-driver)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/teamtnt/laravel-scout-tntsearch-driver/master.svg?style=flat-square)](https://travis-ci.org/teamtnt/laravel-scout-tntsearch-driver)
[![Quality Score](https://img.shields.io/scrutinizer/g/teamtnt/laravel-scout-tntsearch-driver.svg?style=flat-square)](https://scrutinizer-ci.com/g/teamtnt/laravel-scout-tntsearch-driver)
[![Total Downloads](https://img.shields.io/packagist/dt/teamtnt/laravel-scout-tntsearch-driver.svg?style=flat-square)](https://packagist.org/packages/teamtnt/laravel-scout-tntsearch-driver)

This package makes it easy to add full text search support to your models with Laravel 5.3 to 13.0.

## Premium products

If you find TNT Search to be one of your valuable assets, take a look at one of our premium products

[<img src="https://i.imgur.com/ujagviB.png" width="420px" />](https://analytics.tnt.studio)


## Support us on Open Collective

- [TNTSearch](https://opencollective.com/tntsearch)

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

For Laravel 11+, add the Scout and TNTSearch service providers to `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    // ...
    Laravel\Scout\ScoutServiceProvider::class,
    TeamTNT\Scout\TNTSearchScoutServiceProvider::class,
];
```

For earlier versions of Laravel, add the service provider as follows:

```php
// config/app.php
'providers' => [
    // ...
	Laravel\Scout\ScoutServiceProvider::class,
    TeamTNT\Scout\TNTSearchScoutServiceProvider::class,
],
```

Add  `SCOUT_DRIVER=tntsearch` to your `.env` file

Then you should publish `scout.php` configuration file to your config directory

```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

In your `config/scout.php` add:

```php

'tntsearch' => [
    // Directory where the TNTSearch index files (SQLite .index files)
	// are written and read from. Defaults to the app's storage path,
	// but can be overridden via SCOUT_PATH if indexes should live
	// elsewhere (e.g. a shared/mounted volume across instances)
    'storage'  => storage_path(),

	// Master switch for fuzzy (typo-tolerant) matching. When a query returns
	// no exact hits (or 'no_limit' is true), terms are matched by
	// Levenshtein distance instead of an exact match
    'fuzziness' => env('TNTSEARCH_FUZZINESS', false),

    'fuzzy' => [
		// Number of leading characters of the search term that must
		// match exactly before fuzzy comparison kicks in. Higher values
		// narrow the candidate word list and speed up fuzzy lookups.
        'prefix_length' => 2,

		// Caps how many candidate terms (from the index wordlist,
		// ordered by popularity) are pulled and Levenshtein-compared
		// against the search term. Lower is faster but may miss valid
		// fuzzy matches; higher is more thorough but slower.
        'max_expansions' => 50,

		// Maximum Levenshtein (edit) distance — the number of single
		// character insertions, deletions or substitutions — allowed
		// between the search term and a candidate indexed term for it
		// to still count as a match. Lower is stricter (fewer typos
		// tolerated); higher allows looser, less precise matches.
        'distance' => 2,

		// When true, fuzzy matching runs even if the exact search
		// already found results, so fuzzy matches are always added to
		// the result set. When false, fuzzy matching only kicks in as
		// a fallback when the exact search finds nothing.
	    'no_limit' => true,
    ],
	
	// When true, the last word of the query is treated as an
	// incomplete word being typed: instead of requiring an exact
	// match, it is matched as a prefix (term LIKE 'word%') against the
	// wordlist, picking the shortest/most popular matching term. All
	// earlier words in the query still require exact matches. Useful
	// for live/instant search inputs where the final word isn't
	// finished yet.
    'asYouType' => false,

	// When true, queries are parsed as boolean expressions instead of
	// being ranked by relevance: spaces between words mean AND, " or "
	// means OR, a leading "-" before a word means NOT/exclude, and
	// parentheses can be used to group terms. Only documents matching
	// the resulting expression are returned (no relevance ranking).
	// 
	// Boolean example:
	//   "php -laravel" -> docs containing "php" but NOT "laravel"
	//   "cat or dog" -> docs containing either "cat" or "dog"
	// 
	// When false (default), queries use TNTSearch's normal
	// free-text search: all words are matched and results are ranked
	// by relevance, with no special meaning for "or" or "-".
	// 
	// Non-boolean example:
	//   "php -laravel" -> ranked results for the literal words "php" and "laravel"
    'searchBoolean' => env('TNTSEARCH_BOOLEAN', false),
	
	// Caps how many documents are fetched per matched keyword from the
	// index (ordered by hit_count, i.e. most relevant first), before
	// ranking/intersecting results. This is a per-term limit, not a
	// limit on final results: if a term matches more than maxDocs
	// documents, only the top maxDocs (by popularity) are considered,
	// which can cause AND/OR boolean searches on very common words to
	// miss legitimate matches. Lower values improve performance on
	// large indexes at the cost of search completeness.
    'maxDocs' => env('TNTSEARCH_MAX_DOCS', 500),
],
```
To prevent your search indexes being commited to your project repository,
add the following line to your `.gitignore` file.

```/storage/*.index```

The `asYouType` option can be set per model basis, see the example below.

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

    public $asYouType = true;

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

If you have a lot of records and want to speed it up you can run (note that with this you can no longer use model-relations in your `toSearchableArray()`):

`php artisan tntsearch:import App\\Post`

After that you can search your models with:

`Post::search('Bugs Bunny')->get();`

## Scout status 

`php artisan scout:status`

With this simple command you'll get a quick overview of your search indices.

![Image of Scout Status Command](https://teamtnt.github.io/img/scout_status.png)

Or you can pass a searchable model argument:

`php artisan scout:status "App\Models\Post"`

If your models are not in the default location `app` or one of its subdirectories, you may set the `modelPath` option
```php
// config/scout.php
'tntsearch' => [
    // ...
    'modelPath' => 'models',
],
```

![Image of Scout Status Command](https://teamtnt.github.io/img/scout_status_single_new.png)

## Constraints

Additionally to `where()` statements as conditions, you're able to use Eloquent queries to constrain your search. This allows you to take relationships into account.

If you make use of this, the search command has to be called after all queries have been defined in your controller.

The `where()` statements you already know can be applied everywhere.

```php
namespace App\Http\Controllers;

use App\Post;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $post = new Post;

        // filter out posts to which the given topic is assigned
        if($request->topic) {
            $post = $post->whereNotIn('id', function($query){
                $query->select('assigned_to')->from('comments')->where('topic','=', request()->input('topic'));
            });
        }

        // only posts from people that are no moderators
        $post = $post->byRole('moderator','!=');

        // when user is not admin filter out internal posts
        if(!auth()->user()->hasRole('admin'))
        {
            $post= $post->where('internal_post', false);
        }

        if ($request->searchTerm) {
            $constraints = $post; // not necessary but for better readability
            $post = Post::search($request->searchTerm)->constrain($constraints);
        }

        $post->where('deleted', false);

        $post->orderBy('updated_at', 'asc');

        $paginator = $post->paginate(10);
        $posts = $paginator->getCollection();

        // return posts
    }
}
```

## Adding via Query
The `searchable()` method will chunk the results of the query and add the records to your search index. 

```php
$post = Post::find(1);

// You may also add record via collection...
$post->searchable();

// OR

$posts = Post::where('year', '>', '2018')->get();

// You may also add records via collections...
$posts->searchable();
```

When using constraints apply it after the constraints are added to the query, as seen in the above example.

## OrderBy
An `orderBy()` statement can now be applied to the search query similar to the `where()` statement.

When using constraints apply it after the constraints are added to the query, as seen in the above example.

## Sponsors

Become a sponsor and get your logo on our README on Github with a link to your site. [[Become a sponsor](https://opencollective.com/tntsearch#sponsor)]

## Credits

- [Nenad Ticaric](https://github.com/nticaric)
- [Sasa Tokic](https://github.com/stokic)
- [All Contributors](../../contributors)

## Contributors

This project exists thanks to all the people who contribute.
<a href="../../graphs/contributors"><img src="https://opencollective.com/laravel-scout-tntsearch-driver/contributors.svg?width=890" /></a>


## Backers

Thank you to all our backers! 🙏 [[Become a backer](https://opencollective.com/laravel-scout-tntsearch-driver#backer)]

<a href="https://opencollective.com/laravel-scout-tntsearch-driver#backers" target="_blank"><img src="https://opencollective.com/laravel-scout-tntsearch-driver/backers.svg?width=890"></a>

