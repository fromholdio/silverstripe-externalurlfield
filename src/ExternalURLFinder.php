<?php

namespace Sunnysideup\ExternalURLField;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBField;

class ExternalURLFinder
{
    /**
     * finds an object with a matching URL.
     * it assumes that links are stored as lower case URLs.
     * without the final slash!
     *
     * @param string $link
     * @param string $className
     * @param string $field
     * @param bool|null $removeFinalSlash
     *
     * @return DataList
     */
    public static function find(string $link, string $className, string $field, ?bool $removeFinalSlash = true): DataList
    {
        $link = strtolower($link);
        if($removeFinalSlash) {
            $link = rtrim($link, '/');
        }
        $linkObject = DBField::create_field('ExternalURL', $link);
        $domain = $linkObject->Domain();
        if(strpos($domain, 'www.') === 0) {
            $domainWWW = $domain;
            $domainNoWWW = $linkObject->Domain()->noWWW();
        } else {
            $domainWWW = 'www' . $domain;
            $domainNoWWW = $domain;
        }
        $items = [
            $domainWWW,
            $domainNoWWW,
            'https://' . $domainWWW,
            'https://' . $domainNoWWW,
            'http://' . $domainWWW,
            'http://' . $domainNoWWW,
        ];
        return $className::get()->filter([$field => $items]);
    }
}
