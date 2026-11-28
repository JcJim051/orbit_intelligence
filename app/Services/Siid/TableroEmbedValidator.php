<?php

namespace App\Services\Siid;

use Illuminate\Support\Str;

class TableroEmbedValidator
{
    public function looker(?string $url): bool
    {
        if (! filled($url) || filter_var($url, FILTER_VALIDATE_URL) === false || ! str_contains((string) parse_url($url, PHP_URL_PATH), '/embed/')) {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return collect(config('siid.looker.allowed_hosts', []))->contains(fn (string $allowed): bool => $host === Str::lower($allowed));
    }

    public function superset(?string $uuid): bool
    {
        return filled($uuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $uuid) === 1;
    }
}
