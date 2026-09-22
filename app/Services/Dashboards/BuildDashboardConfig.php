<?php

namespace App\Services\Dashboards;

use App\Models\Dashboard;
use App\Models\GeoViewer;
use App\Models\TabularDataSource;

class BuildDashboardConfig
{
    /** @return array<string, mixed> */
    public function handle(Dashboard $dashboard, bool $preview = false): array
    {
        $config = $preview ? ($dashboard->draft_config ?? []) : ($dashboard->versions()->where('version', $dashboard->published_version)->first()?->config ?? []);
        $source = ! empty($config['data_source_id']) ? TabularDataSource::query()->with('currentVersion')->find($config['data_source_id']) : null;
        $viewer = ! empty($config['map']['geo_viewer_id']) ? GeoViewer::query()->find($config['map']['geo_viewer_id']) : null;
        if (! $preview && $viewer !== null && ! $viewer->isPublished()) {
            $viewer = null;
        }
        $fields = collect($source?->currentVersion?->fields ?? [])->filter(fn (array $field): bool => $preview || $field['visibility'] !== 'internal')->values()->all();

        return [
            'dashboard' => ['name' => $dashboard->name, 'slug' => $dashboard->slug, 'description' => $dashboard->description, 'version' => $preview ? 'draft' : $dashboard->published_version, 'published_at' => $dashboard->published_at?->toIso8601String()],
            'config' => $config,
            'source' => $source ? ['id' => $source->id, 'name' => $source->name, 'version' => $source->current_version, 'fields' => $fields] : null,
            'map' => $viewer ? ['name' => $viewer->name, 'config_url' => $preview ? route('admin.geo-viewers.preview-config', $viewer, false) : route('geo-viewers.config', $viewer, false)] : null,
            'query_url' => $preview ? route('admin.dashboards.preview-query', $dashboard, false) : route('dashboards.query', $dashboard, false),
        ];
    }
}
