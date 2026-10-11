<?php

namespace Fromholdio\ExternalURLField\Tests;

use Fromholdio\ExternalURLField\ExternalURL;
use Fromholdio\ExternalURLField\Tests\ExternalURLTest\TestObject;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\ModelData;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\View\ViewLayerData;

/**
 * Behaviour of the `ExternalURL` DBField as seen by templates and PHP callers.
 *
 * Template tests render real template strings against a record whose field is
 * declared through `$db`, so values pass through the ORM schema, `$casting`
 * (`Domain` and `URL` cast back to `ExternalURL`) and the template engine,
 * exactly as `$Website.Domain.NoWWW` does on a site.
 */
class ExternalURLTest extends SapphireTest
{
    protected $usesDatabase = false;

    protected const README_URL = 'http://username:password@www.hostname.com:81/path?arg=value#anchor';

    // ---------------------------------------------------------------- Template rendering

    /**
     * The README's `$Website.Domain` line is not asserted here: `Domain` is cast to
     * `ExternalURL`, whose `forTemplate()` renders `URL()` and so prefixes `http://`.
     * That predates the NoWWW fix and is left for a separate decision.
     */
    public static function provideReadmeTemplateExample(): array
    {
        return [
            'Website' => ['$Website', self::README_URL],
            'Website.Nice' => ['$Website.Nice', 'www.hostname.com/path'],
            'Website.Domain.NoWWW' => ['$Website.Domain.NoWWW', 'hostname.com'],
        ];
    }

    /**
     * Lines of the README "DataObject / Template Usage" example render as documented.
     */
    #[DataProvider('provideReadmeTemplateExample')]
    public function testReadmeTemplateExample(string $template, string $expected): void
    {
        $record = TestObject::create(['Website' => self::README_URL]);
        $this->assertSame($expected, $this->renderTemplate($template, $record));
    }

    public static function provideDomainNoWWW(): array
    {
        return [
            'full http url with www' => ['http://www.example.com/path', 'example.com'],
            'full https url with www, port, query and fragment' => [
                'https://www.example.com:8443/path?arg=value#anchor',
                'example.com',
            ],
            'scheme-less value with www' => ['www.example.com/path', 'example.com'],
            'host without www' => ['https://example.com/path', 'example.com'],
        ];
    }

    /**
     * `$Website.Domain.NoWWW` renders a bare hostname, never with a scheme added.
     */
    #[DataProvider('provideDomainNoWWW')]
    public function testDomainNoWWWRendersBareHostname(string $website, string $expected): void
    {
        $record = TestObject::create(['Website' => $website]);
        $this->assertSame($expected, $this->renderTemplate('$Website.Domain.NoWWW', $record));
    }

    public function testNoWWWTemplateKeepsSchemelessValueSchemeless(): void
    {
        $record = TestObject::create(['Website' => 'www.example.com/path/page?arg=value#anchor']);
        $this->assertSame(
            'example.com/path/page?arg=value#anchor',
            $this->renderTemplate('$Website.NoWWW', $record)
        );
    }

    public function testNoWWWTemplateKeepsSchemeOfFullURL(): void
    {
        $record = TestObject::create(['Website' => 'https://user:pass@www.example.com:8443/path?arg=value#anchor']);
        $this->assertSame(
            'https://user:pass@example.com:8443/path?arg=value#anchor',
            $this->renderTemplate('$Website.NoWWW', $record)
        );
    }

    // ---------------------------------------------------------------- PHP casting

    public function testDomainIsCastToExternalURL(): void
    {
        $record = TestObject::create(['Website' => self::README_URL]);
        $domain = $record->obj('Website')->obj('Domain');

        $this->assertInstanceOf(ExternalURL::class, $domain);
        $this->assertSame('www.hostname.com', $domain->getValue());
        $this->assertSame('hostname.com', $domain->NoWWW());
    }

    public static function provideNoWWW(): array
    {
        return [
            'bare hostname' => ['www.hostname.com', 'hostname.com'],
            'scheme-less hostname and path' => [
                'www.example.com/path/page?arg=value&other=2#anchor',
                'example.com/path/page?arg=value&other=2#anchor',
            ],
            'scheme-less hostname with port' => ['www.example.com:8080/path', 'example.com:8080/path'],
            'http hostname only' => ['http://www.example.com', 'http://example.com'],
            'http with path' => ['http://www.example.com/path/page', 'http://example.com/path/page'],
            'https with every component' => [
                'https://user:pass@www.example.com:8443/path/page?arg=value&other=2#anchor',
                'https://user:pass@example.com:8443/path/page?arg=value&other=2#anchor',
            ],
            'https without www is unchanged' => ['https://example.com/path', 'https://example.com/path'],
            'empty value' => ['', ''],
        ];
    }

    /**
     * NoWWW only strips a leading www label: a value without a scheme stays
     * scheme-less, and a full URL keeps its scheme and every other component.
     */
    #[DataProvider('provideNoWWW')]
    public function testNoWWW(string $value, string $expected): void
    {
        $field = DBField::create_field('ExternalURL', $value);

        $this->assertInstanceOf(ExternalURL::class, $field);
        $this->assertSame($expected, $field->NoWWW());
    }

    // ---------------------------------------------------------------- Helpers

    protected function renderTemplate(string $template, ModelData $model): string
    {
        return SSTemplateEngine::create()->renderString($template, ViewLayerData::create($model), [], false);
    }
}
