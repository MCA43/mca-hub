<?php

namespace Mca\Hub\Support;

final class McaHubLocale
{
    public static function apply(): void
    {
        $locale = config('hub.locale');
        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);
        }
    }
}
