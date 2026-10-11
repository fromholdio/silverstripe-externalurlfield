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
        ];
    }

    /**
     * Input that cannot form a valid URI fails validation, even on an optional field,
     * rather than throwing out of `setValue()` or being saved as an empty value. The
     * field's value stays empty, so the input is never stored without the configured
     * parts, such as credentials, removed. The input is redisplayed, so submitting the
     * form again fails validation again instead of clearing a saved url.
     */
    #[DataProvider('provideInvalidInput')]
    public function testInvalidInputFailsValidation(string $input): void
    {
        $field = ExternalURLField::create('Website');
        $field->setValue($input);

        $this->assertSame('', $field->getValue());
        $this->assertSame('', $field->dataValue());
        $this->assertSame($input, $field->getFormattedValue());
        $this->assertSame($input, $field->getAttributes()['value']);
        $this->assertFieldIsInvalid($field);
    }

    /**
     * Input `parse_url` cannot read, such as a port out of range, used to be cleared
     * without a message. It now fails validation the same way as other input the
     * field cannot rebuild.
     */
    public function testUnparseableInputFailsValidation(): void
    {
        $field = ExternalURLField::create('Website');
        $field->setValue('http://example.com:99999/path');

        $this->assertSame('', $field->getValue());
        $this->assertFieldIsInvalid($field);
    }

    /**
     * Invalid input fails validation however the regular expression is configured,
     * because the field could not rebuild the url at all.
     */
    #[DataProvider('provideRegexConfig')]
    public function testInvalidInputFailsValidationWithAnyRegex(?string $regex): void
    {
        $field = ExternalURLField::create('Website');
        $this->configureRegex($field, $regex);
        $field->setValue('http://user:pass@exa\\mple.com/path');

        $this->assertSame('', $field->getValue());
        $this->assertFieldIsInvalid($field);
    }

    public function testCorrectedInputValidatesAfterInvalidInput(): void
    {
        $field = ExternalURLField::create('Website');
        $field->setValue('http://exa mple.com/path');
        $this->assertFieldIsInvalid($field);

        $field->setValue('https://user:pass@example.com/path');
        $this->assertSame('https://example.com/path', $field->getValue());
        $this->assertSame('https://example.com/path', $field->getFormattedValue());
        $this->assertTrue($field->validate()->isValid());
    }

    public static function provideClearedValue(): array
    {
        return [
            'empty string' => [''],
            'null' => [null],
        ];
    }

    /**
     * Clearing the field after invalid input is an intentional empty value, so it
     * validates.
     */
    #[DataProvider('provideClearedValue')]
    public function testClearedValueValidatesAfterInvalidInput(?string $cleared): void
    {
        $field = ExternalURLField::create('Website');
        $field->setValue('http://exa mple.com/path');
        $this->assertFieldIsInvalid($field);

        $field->setValue($cleared);
        $this->assertSame($cleared, $field->getFormattedValue());
        $this->assertTrue($field->validate()->isValid());
    }

    // ---------------------------------------------------------------- Host removal

    public static function provideRegexConfig(): array
    {
        return [
            'default regex' => ['default'],
            'custom regex accepting anything' => ['%.*%'],
            'regex disabled' => [null],
        ];
    }

    public static function provideHostRemovalWithoutReplacement(): array
    {
        $cases = [];
        foreach (static::provideRegexConfig() as $regexName => [$regex])
        {
            $cases["http, $regexName"] = ['http://www.example.com/path', $regex];
            $cases["https, $regexName"] = ['https://www.example.com/path', $regex];
            $cases["scheme-less, $regexName"] = ['www.example.com/path', $regex];
        }
        return $cases;
    }

    /**
     * Removing the host leaves an http(s) url with no host. The field rejects it,
     * rather than letting the uri builder fill in `localhost`, a destination nobody
     * entered or configured.
     */
    #[DataProvider('provideHostRemovalWithoutReplacement')]
    public function testHostRemovalWithoutReplacementFailsValidation(string $input, ?string $regex): void
    {
        $field = ExternalURLField::create('Website');
        $field->setConfig('removeparts', ['host' => true]);
        $this->configureRegex($field, $regex);
        $field->setValue($input);

        $this->assertStringNotContainsString('localhost', (string) $field->getValue());
        $this->assertSame('', $field->getValue());
        $this->assertFieldIsInvalid($field);
    }

    /**
     * A host configured in `defaultparts` replaces the removed host.
     */
    #[DataProvider('provideRegexConfig')]
    public function testHostRemovalWithConfiguredReplacement(?string $regex): void
    {
        $field = ExternalURLField::create('Website');
        $field->setConfig('removeparts', ['host' => true]);
        $field->setConfig('defaultparts', ['host' => 'example.org']);
        $this->configureRegex($field, $regex);
        $field->setValue('https://www.example.com/path?arg=value');

        $this->assertSame('https://example.org/path?arg=value', $field->getValue());
        $this->assertTrue($field->validate()->isValid());
    }

    // ---------------------------------------------------------------- Helpers

    /**
     * @param string|null $regex `default` keeps the field's default regex, null disables it
     */
    protected function configureRegex(ExternalURLField $field, ?string $regex): void
    {
        if ($regex === 'default')
        {
            return;
        }
        $field->setConfig('validregex', $regex);
    }

    protected function assertFieldIsInvalid(ExternalURLField $field): void
    {
        $result = $field->validate();
        $this->assertFalse($result->isValid());

        $messages = $result->getMessages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame('Website', $message['fieldName']);
        $this->assertSame('Please enter a valid URL', $message['message']);
    }
}
