<?php

namespace App\Http\Controllers;

use App\Enums\DatasetStatus;
use App\Enums\GeoLayerAccessPolicy;
use App\Models\GeoLayer;
use App\Models\SpatialDataset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublicSpatialDatasetGeoJsonController extends Controller
{
    public function __invoke(SpatialDataset $spatialDataset): JsonResponse
    {
        abort_unless($spatialDataset->status === DatasetStatus::Active && $spatialDataset->physical_table, 404);
        $layer = GeoLayer::query()->where('slug', $spatialDataset->slug)->first();
        abort_if(! auth()->check() && ($layer === null || $layer->access_policy === GeoLayerAccessPolicy::Pending), 404);
        $table = '"'.str_replace('"', '""', $spatialDataset->physical_table).'"';
        $result = DB::selectOne(<<<SQL
            SELECT jsonb_build_object(
                'type', 'FeatureCollection',
                'features', COALESCE(jsonb_agg(jsonb_build_object(
                    'type', 'Feature',
                    'id', t.id,
                    'geometry', ST_AsGeoJSON(ST_Transform(t.geom, 4326))::jsonb,
                    'properties', to_jsonb(t) - 'geom'
                )), '[]'::jsonb)
            ) AS geojson
            FROM publication.{$table} t
            SQL);

        return response()->json(json_decode((string) $result->geojson, true, flags: JSON_THROW_ON_ERROR))
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
