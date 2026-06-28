<?php

use Mca\Hub\McaHub;

if (! function_exists('mca_hub')) {
    /** @param  array<string, string|int>  $replace */
    function mca_hub(string $key, array $replace = []): string
    {
        return (string) __('mca-hub::hub.'.$key, $replace);
    }
}

if (! function_exists('mca_hub_register')) {
    /** @param  array<string, mixed>  $meta */
    function mca_hub_register(string $slug, array $meta): void
    {
        McaHub::register($slug, $meta);
    }
}
