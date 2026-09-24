<?php

return [
    'refresh_hours' => (int) env('OPEN_DATA_REFRESH_HOURS', 6),
    'maximum_features' => (int) env('OPEN_DATA_MAXIMUM_FEATURES', 5000),
    'maximum_filters' => 4,
    'maximum_filter_options' => 100,
    'maximum_popup_fields' => 12,
];
