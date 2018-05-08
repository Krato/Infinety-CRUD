# Infinety CRUD

[![Latest Version on Packagist](https://img.shields.io/packagist/v/krato/crud.svg?style=flat-square)](https://packagist.org/packages/krato/crud)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/krato/crud/master.svg?style=flat-square)](https://travis-ci.org/krato/crud)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/krato/crud.svg?style=flat-square)](https://scrutinizer-ci.com/g/krato/crud/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/krato/crud.svg?style=flat-square)](https://scrutinizer-ci.com/g/krato/crud)
[![Total Downloads](https://img.shields.io/packagist/dt/krato/crud.svg?style=flat-square)](https://packagist.org/packages/krato/crud)

Quickly build an admin interface for your Eloquent models, using Laravel 5. Erect a complete CMS at 10 minutes/model, max.

## Install

Via Composer

``` bash
$ composer require infinety-es/crud/
```

Add this under service providers array on config/app.php
```php
'Jenssegers\Date\DateServiceProvider',
```


Add this to your config/app.php, under "aliases":

```php
'CRUD' => 'Infinety\CRUD\CrudServiceProvider',
'Date' => Jenssegers\Date\Date::class,
```

## Usage

In short:

1. Create a controller that extends CrudController.

2. Make your model use the CrudTrait.

3. Create a new resource route.

4. **(optional)** Define your validation rules in a Request files.

## Data Types

### Images:

On column array add this to tell the crud is an image:

 ```php
  'type'  => 'image'
 ```

### Date

On column array add this to tell the crud is an date:

 ```php
  'type' => 'date',
  'language' => 'es', //Language set
  'format'   => 'd-m-Y' //Custom date format
 ```
You can format a Date object like the DateTime object (http://www.php.net/manual/en/function.date.php):

## ToDO

Add documentation

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email hello@ericlagarda.com instead of using the issue tracker.

## Credits

- [Infinety][http://www.infinety.es]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/krato/crud.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/krato/crud/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/krato/crud.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/krato/crud.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/krato/crud.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/krato/crud
[link-travis]: https://travis-ci.org/krato/crud
[link-scrutinizer]: https://scrutinizer-ci.com/g/krato/crud/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/krato/crud
[link-downloads]: https://packagist.org/packages/krato/crud
[link-author]: https://github.com/krato
[link-contributors]: ../../contributors
