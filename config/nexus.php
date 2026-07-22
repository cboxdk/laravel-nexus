<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | US tax dataset (us-tax-data)
    |--------------------------------------------------------------------------
    |
    | Where the shipped NexusThresholdSource reads economic-nexus thresholds from:
    | the compiled us-tax-data dataset's `by-section/nexus.json`. An http(s) base
    | URL (the public mirror, the default) or a local directory. Fetched sections
    | are cached for `ttl` seconds. Point it at a pinned tag or a local copy for an
    | offline/deterministic build; an unreachable location denies (no threshold),
    | never guesses.
    |
    */

    'us_tax_data' => [
        'location' => env('NEXUS_US_DATASET_LOCATION', 'https://raw.githubusercontent.com/cboxdk/us-tax-dataset/main'),
        'ttl' => (int) env('NEXUS_US_DATASET_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Approaching band
    |--------------------------------------------------------------------------
    |
    | The fraction of a state's threshold at which the engine flags it as
    | "approaching" (a watch signal before it triggers). 0.8 = 80%.
    |
    */

    'approaching_ratio' => (float) env('NEXUS_APPROACHING_RATIO', 0.8),

];
