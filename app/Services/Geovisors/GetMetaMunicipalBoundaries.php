<?php

namespace App\Services\Geovisors;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GetMetaMunicipalBoundaries
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return Cache::remember('geovisors:meta-municipal-boundaries:v1', now()->addDays(30), function (): array {
            return Http::acceptJson()
                ->timeout(30)
                ->retry(2, 500)
                ->get(config('investments.meta_boundaries_url'))
                ->throw()
                ->json();
        });
    }
}
