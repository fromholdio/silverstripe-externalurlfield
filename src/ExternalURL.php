<?php

namespace Sunnysideup\ExternalURLField;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\FieldType\DBVarchar;
use RuntimeException;

class ExternalURL extends DBVarchar
{
    private static $casting = [
        'Domain' => ExternalURL::class,
        'DomainShort' => 'Varchar',
        'URL' => ExternalURL::class,
        'Path' => 'Varchar',
    ];

    /**
     * 2083 is the lowest common denominator when it comes to url lengths.
     * however, 768 allows searching...
     *
     * @param null|mixed $name
     * @param mixed      $size
     * @param mixed      $options
     */
    public function __construct($name = null, $size = 2083, $options = [])
    {
        parent::__construct($name, $size, $options);
    }

    /**
     * Remove ugly parts of a url to make it nice.
     */
    public function Nice(): string
    {
        if ($this->value) {
            $parts = parse_url($this->URL());
            if ($parts) {
                $remove = ['scheme', 'user', 'pass', 'port', 'query', 'fragment'];
                foreach ($remove as $part) {
                    unset($parts[$part]);
                }
            }

            return rtrim(http_build_url($parts), '/');
        }

        return '';
    }

    /**
     * Get just the domain of the url.
     */
    public function Domain()
    {
        if ($this->value) {
            return parse_url($this->URL(), PHP_URL_HOST);
        }

        return '';
    }

    /**
     * Get just the domain of the url without the www subdomain and without https://.
     */
    public function DomainShort()
    {
        if ($this->value) {
            $parsedUrl = parse_url($this->URL());
            $host = $parsedUrl['host'] ?? '';
            // Remove 'www.' if present.
            $domain = preg_replace('/^www\./', '', $host);
            return $domain;
        }

        return '';
    }

    /**
     * Remove the www subdomain, if present.
     */
    public function NoWWW()
    {
        //https://stackoverflow.com/questions/23349257/trim-a-full-string-instead-of-using-trim-with-characters
        return $url = preg_replace('/^(www\.)*/', '', (string) $this->value);
    }

    public function Path()
    {
        if ($this->value) {
            return trim((string) parse_url((string) $this->URL(), PHP_URL_PATH), '/');
        }

        return '';
    }

    /**
     * Scaffold the ExternalURLField for this ExternalURL.
     *
     * @param null|mixed $title
     * @param null|mixed $params
     */
    public function scaffoldFormField($title = null, $params = null)
    {
        $field = new ExternalURLField($this->name, $title);
        $field->setMaxLength($this->getSize());

        return $field;
    }

    public function forTemplate()
    {
        if ($this->value) {
            return (string) $this->URL();
        }

        return '';
    }

    public function saveInto($dataObject)
    {
        $url = (string) $this->value;
        if ($url) {
            $config = Config::inst()->get(ExternalURLField::class, 'default_config');
            $defaults = $config['defaultparts'];
            if (!preg_match('#^[a-zA-Z]+://#', $url)) {
                $url = $defaults['scheme'] . '://' . $url;
            }

            $parts = parse_url($url);
            if ($parts) {
                //can't parse url, abort

                foreach (array_keys($parts) as $part) {
                    if (true === $config['removeparts'][$part]) {
                        unset($parts[$part]);
                    }
                }

                // this causes errors!
                // $parts = array_filter($defaults, fn ($default) => ! isset($parts[$part]));

                $this->value = rtrim(http_build_url($defaults, $parts), '/');
            } else {
                $this->value = '';
            }
        } else {
            $this->value = '';

        }
        parent::saveInto($dataObject);
    }
}
