<?php

$frameAncestors = array_values(array_filter(array_map(
    'trim',
    explode(',', env('GEOVISOR_FRAME_ANCESTORS', "'self',https://meta.gov.co,https://www.meta.gov.co"))
)));

return [
    'frame_ancestors' => $frameAncestors,
];
