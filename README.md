# Datatables library for PHP 
[![Latest Stable Version](https://poser.pugx.org/ichadhr/datatables/v/stable)](https://packagist.org/packages/ichadhr/datatables) [![PHP Composer](https://github.com/ichadhr/datatables/actions/workflows/tests.yml/badge.svg)](https://github.com/ichadhr/datatables/actions/workflows/tests.yml) [![license](https://img.shields.io/github/license/mashape/apistatus.svg)](https://github.com/ichadhr/datatables/blob/master/LICENCE) 

Datatables server-side processing.

## Features  
* Easy to use. Generates json using only a few lines of code.
* Editable columns with a closure function.
* Supports custom filters.
* Can handle most complicated queries.
* Supports mysql and sqlite for native php.

## Installation

> **NOTE:** version 1.0+ requires php 7.1.3+ ([php supported versions](http://php.net/supported-versions.php))

The recommended way to install the library is with [Composer](https://getcomposer.org/)

If you haven't started using composer, I highly recommend you to use it.

Put a file named `composer.json` at the root of your project, containing this information: 

    {
        "require": {
           "ichadhr/datatables": "1.*"
        }
    }

And then run: 

```
composer install
```

Or just run : 

```
composer require ichadhr/datatables
```

Add the autoloader to your project:

```php
    <?php

    require_once 'vendor/autoload.php';
```

You're now ready to begin using the Datatables php library.

```php
    <?php
    require_once 'vendor/autoload.php';

    use Ichadhr\Datatables\Datatables;
    use Ichadhr\Datatables\DB\MySQL;

    $config = [ 'host'     => 'localhost',
                'port'     => '3306',
                'username' => 'homestead',
                'password' => 'secret',
                'database' => 'sakila' ];

    $dt = new Datatables( new MySQL($config) );

    $dt->query('Select film_id, title, description from film');

    echo $dt->generate();
```
If you are using a php framework such as codeigniter or laravel, you can use the relevant database adapter.

```php
// Codeigniter 4 Example

<?php

namespace App\Controllers;

use Config\Database;
use Ichadhr\Datatables\Datatables;
use Ichadhr\Datatables\DB\Codeigniter4Adapter;

class Home extends BaseController
{
    public function index()
    {
        return view('index');
    }

    public function ajax()
    {
        // CI 4 builder class
        $db = Database::connect();

        $builder = $db->table('Track');
        $builder->select('TrackId, Name, UnitPrice');

        // Datatables Php Library
        $datatables = new Datatables(new Codeigniter4Adapter);

        // using CI4 Builder
        $datatables->query($builder);

        // alternatively plain sql
        // $datatables->query('Select TrackId, Name, UnitPrice from Track');

        return $this->response->setJSON($datatables->generate()->toJson());
    }
}
```

```php
// Laravel Example

<?php
// routes/web.php 

use Ichadhr\Datatables\Datatables;
use Ichadhr\Datatables\DB\LaravelAdapter;

Route::get('/ajax/laravel', function () {

    $dt = new Datatables(new LaravelAdapter);

    $dt->query(
      Track::query()
          ->select([
              'TrackId',
              'Track.Name',
              'Title as Album',
              'MediaType.Name as MediaType',
              'UnitPrice',
              'Milliseconds',
              'Bytes',
          ])
          ->join('Album', 'Album.AlbumId', 'Track.AlbumId')
          ->join('MediaType', 'MediaType.MediaTypeId', 'Track.MediaTypeId')
    ); // same as the previous example, sql statement can be used.

    return $dt->generate();
});

```

## Methods
This is the list of available public methods.

__query($query)__ *required*

* sets the sql query

__generate()__  *required*

* runs the queries and build outputs
* returns the output as json
* same as generate()->toJson()
* accepts 'debug' as an optional argument to include detailed error messages and debug info in the response

__toJson()__

* returns the output as json
* should be called after generate()

__toArray()__

* returns the output as array
* should be called after generate()

__add($column, function( $row ){})__

* adds extra columns for custom usage

__edit($column, function($row){})__

* allows column editing

__filter($column, function(){})__

* allows custom filtering
* it has the methods below
    - escape($value)
    - searchValue()
    - defaultFilter()
    - between($low, $high)
    - whereIn($array)
    - greaterThan($value)
    - lessThan($value)

__hide($columns)__

* removes the column from output
* It is useful when you only need to use the data in add() or edit() methods.

__setDistinctResponseFrom($column)__

* executes the query with the given column name and adds the returned data to the output with the distinctData key.

__setDistinctResponse($output)__

* adds the given data to the output with the distinctData key.

__getColumns()__

* returns column names (for dev purpose)

__getQuery()__

* returns the sql query string that is created by the library (for dev purpose)


## Example

```php
    <?php
    require_once 'vendor/autoload.php';

    use Ichadhr\Datatables\Datatables;
    use Ichadhr\Datatables\DB\SQLite;

    $path = __DIR__ . '/../path/to/database.db';
    $dt = new Datatables( new SQLite($path) );

    $dt->query('Select id, name, email, age, address, plevel from users');

    $dt->edit('id', function($data){
        // return a link.
        return "<a href='user.php?id=" . $data['id'] . "'>edit</a>";
    });

    $dt->edit('email', function($data){
        // masks email : mail@mail.com => m***@mail.com
        return preg_replace('/(?<=.).(?=.*@)/u','*', $data['email']);
    });

    $dt->edit('address', function($data){
        // checks user access.
        $current_user_plevel = 4;
        if ($current_user_plevel > 2 && $current_user_plevel > $data['plevel']) {
            return $data['address'];
        }

        return 'you are not authorized to view this column';
    });
    
    $dt->hide('plevel'); // hides 'plevel' column from the output

    $dt->add('action', function($data){
        // returns a link in a new column
        return "<a href='user.php?id=" . $data['id'] . "'>edit</a>";
    });

    $dt->filter('age', function (){
        // applies custom filtering.
        return $this->between(15, 30);
    });

    echo $dt->generate()->toJson(); // same as 'echo $dt->generate()'
    // or use debug mode to show debug information:
    echo $dt->generate('debug')->toJson(); // includes debug info and detailed error messages in the response

```

## Road Map
* better test suites for each class
* improve integrations for php frameworks

## Requirements
Composer  
DataTables > 1.10  
PHP > 7.1.3

## License
[the MIT license](https://github.com/ichadhr/Datatables/blob/master/LICENCE)


