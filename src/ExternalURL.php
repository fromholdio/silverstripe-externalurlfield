<?php

namespace Fromholdio\ExternalURLField;

use SilverStripe\Forms\FormField;
use SilverStripe\ORM\FieldType\DBVarchar;

class ExternalURL extends DBVarchar
{
    private static $casting = array(
        "Domain" => "ExternalURL",
        "URL" => "ExternalURL"
    );

    /**
     * 2083 is the lowest common denominator when it comes to url lengths.
     */
    public function __construct($name = null, $size = 2083, $options = array())
    {
        parent::__construct($name, $size, $options);
    }

    /**
     * Remove ugly parts of a url to make it nice
     */
    public function Nice(): string
    {
        $nice = '';
        if (!empty($this->value)) {
            $parts = parse_url($this->URL());
            if (!empty($parts))
            {
                $remove = ['scheme', 'user', 'pass', 'port', 'query', 'fragment'];
                foreach ($remove as $part) {
                    unset($parts[$part]);
                }
                $nice = http_build_url($parts);
            }
        }
        return $nice;
    }

    /**
     * Get just the domain of the url.
     */
    public function Domain(): string
    {
        return empty($this->value) ? '' : parse_url($this->URL(), PHP_URL_HOST);
    }

    /**
     * Remove the www subdomain, if present.
     */
    public function NoWWW(): string
    {
        $url = '';
        if (!empty($this->Value)) {
            $parts = parse_url($this->URL());
            if (!empty($parts))
            {
                if (isset($parts['host'])) {
                    $parts['host'] = preg_replace('#^www\.(.+\.)#i', '$1', $parts['host']);
                }
                $url = http_build_url($parts);
            }
        }
        return $url;
    }

    /**
     * Scaffold the ExternalURLField for this ExternalURL
     */
    public function scaffoldFormField(?string $title = null, array $params = []): ?FormField
    {
        $field = ExternalURLField::create($this->name, $title);
        $field->setMaxLength($this->getSize());
        return $field;
    }

    public function forTemplate(): string
    {
        return empty($this->value) ? '' : nl2br($this->URL() ?? '');
    }
}
