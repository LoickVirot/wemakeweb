<?php

namespace AppBundle\Utils;

/**
 * Created by PhpStorm.
 * User: Loïck
 * Date: 07/09/2017
 * Time: 22:12
 */
class UrlUtils
{
    static public function slugify($text)
    {
        if (empty($text)) {
            throw new \InvalidArgumentException("Text can't be empty");
        }

        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        return $text;
    }
}