# SilverStripe External URL Field

Provides a `DBField` and `FormField` for handling external URLs.

Validate and tidy urls as they are captured from users. Configuration is highly flexible. Makes use of php's `parse_url` and the `Uri` class from [guzzlehttp/psr7](https://github.com/guzzle/psr7) to do the actual work.

## Installation

Note - this is forked from [burnbright/silverstripe-externalurlfield](https://github.com/burnbright/silverstripe-externalurlfield).

```sh
composer require fromholdio/silverstripe-externalurlfield "*@stable"
```

## Requirements

* SilverStripe ^6
* guzzlehttp/psr7 ^2.13.1 (also required by SilverStripe framework; earlier 2.x releases accept malformed hosts that the field's validation relies on Guzzle to reject)

Earlier versions built urls with the abandoned [jakeasmith/http_build_url](https://github.com/jakeasmith/http_build_url) polyfill. This module no longer requires it, so projects that call `http_build_url` themselves need to require it directly.

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

### How urls are tidied

Urls saved by the field, and the `Nice` and `NoWWW` template output, are built as valid URIs:

* the scheme and host are lowercased, so `HTTPS://WWW.Example.COM/Path` becomes `https://www.example.com/Path`
* a port that is the default for the scheme is left out, so `https://example.com:443/` becomes `https://example.com/`
* characters that are not allowed in a url are percent-encoded, so `/a b` becomes `/a%20b`, while existing encoding such as `%20` is kept as it is
* a query or fragment of `0`, such as `#0`, is kept

Input that cannot form a valid url fails the field's validation with "Please enter a valid URL", even when the field is optional and whatever `validregex` is set to. This covers a host containing a space or backslash, input `parse_url` cannot read such as a port above 65535, and an `http` or `https` url left without a host because `removeparts` removes it and `defaultparts` sets no replacement host. Submitting an empty field is still an intentional clear. `Nice` and `NoWWW` return an empty string for a stored value that cannot form a valid url.

### Rejected input

The field keeps rejected input so it can be corrected, without saving it through its own save path:

* the field redisplays the input with the validation message, including after the redirect that follows a failed form submission, so submitting the form again unchanged fails again rather than clearing a saved url
* `getValue()` stays empty, while `dataValue()` and `Form::getData()` return the input as entered, credentials included, because that is the form data the framework holds in the session to restore the form
* the field's `saveInto()` leaves the record's url unchanged for rejected input, so `Form::saveInto()` neither saves it nor clears the saved url, even when called without validating

Accepted input is rebuilt with the parts set in `removeparts` removed, including in `dataValue()` and `Form::getData()`. For rejected input, validation is the only protection on other paths: a `save<FieldName>()` method on the record receives `dataValue()` directly, bypassing the field's `saveInto()`, and so does code that writes `Form::getData()` or `dataValue()` itself. Validate the form before using its data that way.

## Running the tests

The PHPUnit suite lives in `tests/php` and runs on PHP 8.3 and 8.5. The form tests save records to a temporary test database, so the database user must be able to create databases, as `root` can.

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

`SS_PHPUNIT_FLUSH=1` rebuilds the class manifest so the suite's test-only classes are found. If the project's database user cannot create databases, set `SS_DATABASE_USERNAME` and `SS_DATABASE_PASSWORD` for one that can. The GitHub Actions workflow in `.github/workflows/tests.yml` runs the standalone suite against MySQL on both PHP versions.
