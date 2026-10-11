<?php

namespace Fromholdio\ExternalURLField;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\Uri;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\ModelData;

/**
 * ExternalURLField
 *
 * Form field for entering, saving, validating external urls.
 */
class ExternalURLField extends TextField
{
    /**
     * Default configuration
     *
     * URL validation regular expression was sourced from
     * @see https://gist.github.com/dperini/729294
     * @var array
     */
    private static $default_config = [
        'defaultparts' => [
            'scheme' => 'http'
        ],
        'removeparts' => [
            'scheme' => false,
            'user' => true,
            'pass' => true,
            'host' => false,
            'port' => false,
            'path' => false,
            'query' => false,
            'fragment' => false
        ],
        'html5validation' => true,
        'validregex' => '%^(?:(?:https?|ftp)://)(?:\S+(?::\S*)'
            . '?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)'
            . '(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))'
            . '(?::\d+)?(?:[^\s]*)?$%iu'
    ];

    /**
     * @var array
     */
    protected $config;

    public function __construct($name, $title = null, $value = null)
    {
        $this->config = $this->config()->default_config;
        parent::__construct($name, $title, $value);
    }

    public function Type(): string
    {
        return 'url text';
    }

    /**
     * @param string $name
     * @param mixed $val
     */
    public function setConfig($name, $val = null): self
    {
        if (is_array($name) && $val == null) {
            foreach ($name as $n => $value) {
                $this->setConfig($n, $value);
            }

            return $this;
        }
        if (is_array($this->config[$name])) {
            if (!is_array($val)) {
                user_error("The value for $name must be an array");
            }
            $this->config[$name] = array_merge($this->config[$name], $val);
        } elseif (isset($this->config[$name])) {
            $this->config[$name] = $val;
        }

        return $this;
    }

    /**
     * @param String $name Optional, returns the whole configuration array if empty
     * @return mixed|array
     */
    public function getConfig($name = null)
    {
        if ($name) {
            return $this->config[$name] ?? null;
        } else {
            return $this->config;
        }
    }

    /**
     * Set additional attributes
     * @return array Attributes
     */
    public function getAttributes(): array
    {
        $parentAttributes = parent::getAttributes();
        $attributes = [];

        $attributes['inputmode'] = 'url';

        if (!isset($parentAttributes['placeholder'])) {
            $attributes['placeholder'] = $this->config['defaultparts']['scheme'] . "://example.com";
        }

        if ($this->config['html5validation']) {
            $attributes += [
                'type' => 'url', //html5 field type
                'pattern' => 'https?://.+', //valid urls only
            ];
        }

        return [
            ...$parentAttributes,
            ...$attributes
        ];
    }

    /**
     * @param mixed $value URL
     * @param array|ModelData $data {@see Form::loadDataFrom}
     * @return $this
     */
    public function setValue($value, $data = null): self
    {
        if (!empty($value)) {
            $value = $this->rebuildURL($value);
        }
        parent::setValue($value, $data);
        return $this;
    }

    /**
     * Add config scheme, if missing.
     * Remove the parts of the url we don't want.
     * Set any defaults, if missing.
     * Remove any trailing slash, and rebuild.
     * @return string
     */
    protected function rebuildURL($url): string
    {
        $defaults = $this->config['defaultparts'];
        if (!preg_match('#^[a-zA-Z]+://#', $url)) {
            $url = $defaults['scheme'] . "://" . $url;
        }
        $parts = parse_url($url);
        if (!$parts) {
            //can't parse url, abort
            return '';
        }
        foreach ($parts as $part => $value) {
            if ($this->config['removeparts'][$part] === true) {
                unset($parts[$part]);
            }
        }
        //set defaults, if missing
        foreach ($defaults as $part => $default) {
            if (!isset($parts[$part])) {
                $parts[$part] = $default;
            }
        }
        try
        {
            return (string) Uri::fromParts($parts);
        }
        catch (MalformedUriException $e)
        {
            //can't build a valid url from the parts, such as a host containing a space, abort
            return '';
        }
    }

    /**
     * Server side validation, using a regular expression.
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        $this->value = trim($this->value ?? '');
        $regex = $this->config['validregex'];
        if ($this->value && $regex && !preg_match($regex, $this->value ?? '')) {
            $result->addFieldError(
                $this->name,
                _t('ExternalURLField.VALIDATION', "Please enter a valid URL"),
                "validation"
            );
        }
        return $result;
    }
}
