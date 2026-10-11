# SilverStripe External URL Field

Provides a `DBField` and `FormField` for handling external URLs.

Validate and tidy urls as they are captured from users. Configuration is highly flexible. Makes use of php's `parse_url` and `http_build_url` to do the actual work.

## Installation

Note - this is forked from [burnbright/silverstripe-externalurlfield](https://github.com/burnbright/silverstripe-externalurlfield).

```sh
composer require fromholdio/silverstripe-externalurlfield "*@stable"
```

## Requirements

Makes use of the `http_build_url` function from the [PECL pecl_http library](http://php.net/manual/en/ref.http.php). However the module's composer requirements include a [PHP fallback/shim/polyfill](https://github.com/jakeasmith/http_build_url). The composer replacement does check for the presence of `http_build_url`.

* SilverStripe ^6

## DataObject / Template Usage

Handled by `ExternalURL` class (Varchar).

```php
use SilverStripe\ORM\DataObject;

class MyDataObject extends DataObject
{
    private static $db = array(
        'Website' => 'ExternalURL'
    );
}
```

```
<% with $MyDataObject %>
    <p>Website: $Website</p>
    <p>Website Nice: $Website.Nice</p>
    <p>Website Domain: $Website.Domain</p>
    <p>Website Domain No WWW: $Website.Domain.NoWWW</p>
<% end_with %>
```

Given the url `http://username:password@www.hostname.com:81/path?arg=value#anchor`, the above produces:
```
Website: http://username:password@www.hostname.com:81/path?arg=value#anchor
Website Nice: www.hostname.com/path
Website Domain: www.hostname.com
Website Domain No WWW: hostname.com
```

## Form Usage

Handled by `ExternalURLField` (FormField).

Validation is handled by the html5 pattern attribute, and also server side by [a more robust regular expression](https://gist.github.com/dperini/729294).
The field uses the html5 `type="url"` attribute.

You can configure various parts of the url to be stripped out, or populated with defaults when missing.

```php
use Fromholdio\ExternalURLField\ExternalURLField;

//default
$websitefield = new ExternalURLField('Website');

//set options (with defaults shown)
$websitefield->setConfig(array(
    //these will be added, if missing
    'defaultparts' => array(
        'scheme' => 'http'
    ),
    //these parts are removed from saved urls
    'removeparts' => array(
        'scheme' => false,
        'user' => true,
        'pass' => true,
        'host' => false,
        'port' => false,
        'path' => false,
        'query' => false,
        'fragment' => false
    ),
    'html5validation' => true
));

//say you want to store nice tidy facebook urls
$websitefield->setConfig('removeparts',array(
    'query' => true,
    'fragment' => 'true',
    'port' => 'true'
));
//a urls like https://www.facebook.com/joe.bloggs?fref=nf&pnref=story
//would become https://www.facebook.com/joe.bloggs

```

### HTML5 validation

Enabled by default, the html5 validation sets the field type atribute to `url`, and adds a pattern attribute which is set to `https?://.+`.

Disable using the `html5validation` config:
```php
$field->setConfig("html5validation", false);
```

Disabling html5 validation is particularly useful if you want to allow users to enter urls that have no scheme/protocol e.g: `mywebsite.com` instead of `http://www.mywebsite.com`.

## Running the tests

The PHPUnit suite lives in `tests/php` and runs on PHP 8.3 and 8.5. SilverStripe's test bootstrap needs a database connection, even though these tests do not write to it.

From a standalone clone of this module:

```sh
composer install
SS_DATABASE_CLASS=MySQLDatabase SS_DATABASE_SERVER=127.0.0.1 SS_DATABASE_USERNAME=root \
SS_DATABASE_PASSWORD=root SS_DATABASE_NAME=SS_tests vendor/bin/phpunit
```

From a SilverStripe project that has the module installed:

```sh
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit vendor/fromholdio/silverstripe-externalurlfield/tests/php
```

`SS_PHPUNIT_FLUSH=1` rebuilds the class manifest so the suite's test-only classes are found. The GitHub Actions workflow in `.github/workflows/tests.yml` runs the standalone suite against MySQL on both PHP versions.
