<?php

namespace Mca\Hub\Services;

final class HubRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $packages = [];

    /** @param  array<string, mixed>  $meta */
    public function register(string $slug, array $meta): void
    {
        $this->packages[$slug] = array_merge($this->packages[$slug] ?? [], $meta, ['slug' => $slug]);
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->packages;
    }
}
