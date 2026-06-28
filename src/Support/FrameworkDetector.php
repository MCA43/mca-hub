<?php

namespace Mca\Hub\Support;

use Illuminate\Foundation\Application;

final class FrameworkDetector
{
    /** @return list<string> */
    public static function knownFrameworks(): array
    {
        return [
            'laravel11',
            'laravel12',
            'laravel13',
            'codeigniter3',
            'codeigniter4',
        ];
    }

    public static function current(): string
    {
        $override = config('hub.framework');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        if (class_exists(Application::class)) {
            return self::detectLaravel();
        }

        if (defined('CI_VERSION')) {
            $version = (string) CI_VERSION;
            if (str_starts_with($version, '3.')) {
                return 'codeigniter3';
            }
            if (str_starts_with($version, '4.')) {
                return 'codeigniter4';
            }
        }

        return 'unknown';
    }

    public static function label(?string $framework = null): string
    {
        return match ($framework ?? self::current()) {
            'laravel11' => 'Laravel 11',
            'laravel12' => 'Laravel 12',
            'laravel13' => 'Laravel 13',
            'codeigniter3' => 'CodeIgniter 3',
            'codeigniter4' => 'CodeIgniter 4',
            default => 'Unknown',
        };
    }

    /** @param  list<string>  $supported */
    public static function supports(array $supported): bool
    {
        $current = self::current();

        if ($current === 'unknown') {
            return true;
        }

        return in_array($current, $supported, true);
    }

    private static function detectLaravel(): string
    {
        $version = Application::VERSION;

        if (version_compare($version, '13.0', '>=')) {
            return 'laravel13';
        }
        if (version_compare($version, '12.0', '>=')) {
            return 'laravel12';
        }
        if (version_compare($version, '11.0', '>=')) {
            return 'laravel11';
        }

        return 'laravel'.explode('.', $version)[0];
    }
}
