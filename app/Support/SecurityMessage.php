<?php

namespace App\Support;

class SecurityMessage
{
    public static function get(string $key): string
    {
        static $messages = [];
        $locale = app()->getLocale();
        $path = resource_path('js/i18n/'.basename($locale).'.json');
        if (! is_file($path)) {
            $locale = 'en';
            $path = resource_path('js/i18n/en.json');
        }
        $messages[$locale] ??= json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)['security'];

        return $messages[$locale][$key];
    }
}
