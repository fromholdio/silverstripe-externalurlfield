<?php

namespace Fromholdio\ExternalURLField\Tests;

use Fromholdio\ExternalURLField\ExternalURLField;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;

/**
 * How `ExternalURLField` tidies the URLs users enter.
 *
 * Values go through the public `setValue()`, which is what form submissions and
 * `Form::loadDataFrom()` call, so these tests cover the field's rebuild of each
 * URL from its parts: default parts added, unwanted parts removed, and the URL
 * written back out as a valid URI.
 */
class ExternalURLFieldTest extends SapphireTest
{
    protected $usesDatabase = false;

    // ---------------------------------------------------------------- Default configuration

    public static function provideDefaultRebuild(): array
    {
        return [
            'hostname only gets the default scheme' => ['example.com', 'http://example.com'],
            'scheme-less hostname and path get the default scheme' => [
                'www.example.com/path/page',
                'http://www.example.com/path/page',
            ],
            'full url keeps every allowed component' => [
                'https://www.example.com:8443/path/page?arg=value&other=2#anchor',
                'https://www.example.com:8443/path/page?arg=value&other=2#anchor',
            ],
            'user and password are removed' => [
                'https://user:pass@www.example.com/path',
                'https://www.example.com/path',
            ],
            'non-default port is kept' => ['http://example.com:8080/path', 'http://example.com:8080/path'],
            'port zero is kept as entered' => ['http://example.com:0/path', 'http://example.com:0/path'],
            'default port for the scheme is omitted' => ['https://example.com:443/path', 'https://example.com/path'],
            'scheme and host are lowercased, path and query keep their case' => [
                'HTTPS://WWW.Example.COM/Path?Key=Value',
                'https://www.example.com/Path?Key=Value',
            ],
            'characters not allowed in a URL are percent-encoded' => [
                'http://example.com/a b?discount=50%&tags=a+b#frag ment',
                'http://example.com/a%20b?discount=50%25&tags=a+b#frag%20ment',
            ],
            'existing percent-encoding is not encoded again' => [
                'http://example.com/a%20b?name=%C3%A9',
                'http://example.com/a%20b?name=%C3%A9',
            ],
            'non-ASCII path is percent-encoded' => [
                'example.com/caf' . "\u{e9}",
                'http://example.com/caf%C3%A9',
            ],
            'internationalised hostname is kept' => [
                'http://b' . "\u{fc}" . 'cher.de/path',
                'http://b' . "\u{fc}" . 'cher.de/path',
            ],
            'IPv6 host and port are kept' => ['http://[::1]:8080/path', 'http://[::1]:8080/path'],
            'path of zero is kept' => ['http://example.com/0', 'http://example.com/0'],
            'query of zero is kept' => ['http://example.com/?0', 'http://example.com/?0'],
            'fragment of zero is kept' => ['http://example.com/#0', 'http://example.com/#0'],
            'empty query and fragment are dropped' => ['http://example.com/?#', 'http://example.com/'],
        ];
    }

    /**
     * With the default configuration the field adds a missing scheme, strips
     * credentials and writes the URL back out as a valid URI.
     */
    #[DataProvider('provideDefaultRebuild')]
    public function testDefaultRebuild(string $input, string $expected): void
    {
        $field = ExternalURLField::create('Website');
        $field->setValue($input);

        $this->assertSame($expected, $field->getValue());
    }

    public static function provideEmptyValue(): array
    {
        return [
            'empty string' => ['', ''],
            'null' => [null, null],
        ];
    }

    /**
     * Empty values are left alone rather than being given a default scheme.
     */
    #[DataProvider('provideEmptyValue')]
    public function testEmptyValueIsNotRebuilt(?string $input, ?string $expected): void
    {
        $field = ExternalURLField::create('Website');
        $field->setValue($input);

        $this->assertSame($expected, $field->getValue());
    }

    // ---------------------------------------------------------------- Configured parts

    public function testConfiguredDefaultScheme(): void
    {
        $field = ExternalURLField::create('Website');
        $field->setConfig('defaultparts', ['scheme' => 'https']);

        $field->setValue('example.com/path');
        $this->assertSame('https://example.com/path', $field->getValue());

        $field->setValue('http://example.com/path');
        $this->assertSame('http://example.com/path', $field->getValue(), 'An entered scheme is not replaced');
    }

    /**
     * The README's "nice tidy facebook urls" configuration, using real booleans.
     */
    public function testConfiguredRemovalOfQueryFragmentAndPort(): void
    {
        $field = ExternalURLField::create('Website');
        $field->setConfig('removeparts', [
            'query' => true,
            'fragment' => true,
            'port' => true,
        ]);
        $field->setValue('https://www.facebook.com:8443/joe.bloggs?fref=nf&pnref=story#top');

        $this->assertSame('https://www.facebook.com/joe.bloggs', $field->getValue());
    }

    public function testConfiguredRemovalOfPath(): void
    {
        $field = ExternalURLField::create('Website');
        $field->setConfig('removeparts', ['path' => true]);
        $field->setValue('https://example.com/a/b?arg=value');

        $this->assertSame('https://example.com?arg=value', $field->getValue());
    }

    public function testCredentialsKeptWhenNotConfiguredForRemoval(): void
    {
        $field = ExternalURLField::create('Website');
        $field->setConfig('removeparts', ['user' => false, 'pass' => false]);
        $field->setValue('https://user:pass@example.com/path');

        $this->assertSame('https://user:pass@example.com/path', $field->getValue());
    }

    // ---------------------------------------------------------------- Invalid input

    public static function provideInvalidInput(): array
    {
        return [
            'space in hostname' => ['http://exa mple.com/path'],
            'backslash in hostname' => ['exa\\mple.com/path'],
            'credentials before an invalid hostname' => ['http://user:pass@exa\\mple.com/path'],
            'port out of range' => ['http://example.com:99999/path'],
        ];
    }

    /**
     * Input that cannot form a valid URI is cleared, the field's existing response to
     * a URL it cannot parse, rather than throwing out of `setValue()` or being stored
     * without the configured parts removed.
     */
    #[DataProvider('provideInvalidInput')]
    public function testInvalidInputIsClearedWithoutException(string $input): void
    {
        $field = ExternalURLField::create('Website');
        $field->setValue($input);

        $this->assertSame('', $field->getValue());
    }
}
