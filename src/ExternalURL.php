<?php

namespace BurnBright\ExternalURLField;

use SilverStripe\ORM\FieldType\DBVarchar;

class ExternalURL extends DBVarchar
{
    private static $casting = [
        'Domain' => ExternalURL::class,
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
}
