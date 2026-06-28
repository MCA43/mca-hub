<?php

namespace Mca\Hub;

use Mca\Hub\Services\HubRegistry;

final class McaHub
{
    /** @param  array<string, mixed>  $meta */
    public static function register(string $slug, array $meta): void
    {
        if (! app()->bound(HubRegistry::class)) {
            return;
        }

        app(HubRegistry::class)->register($slug, $meta);
    }
}
