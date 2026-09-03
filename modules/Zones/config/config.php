<?php

return [
    'name' => 'Zones',

    /*
    | Where a blank map opens, when the organization has no zones to fit to.
    | Dodoma, because that is where this installation is.
    */
    'default_centre' => ['lat' => -6.163, 'lng' => 35.751],
    'default_zoom' => 12,

    /*
    | What may be zoned, and under whose permission.
    |
    | A whitelist, not a free `{type}/{id}` endpoint: the attach route takes a
    | model name from the browser, and without this an authenticated member
    | could name any class in the application and write pivot rows against it.
    |
    | Each entry names the module that owns the record, so attaching zones to a
    | shipment needs Fulfillment rights rather than Zones rights - being allowed
    | to draw a map is not the same as being allowed to redirect deliveries.
    */
    'zonable' => [
        'servicehub-provider' => [
            'model' => \Modules\ServiceHub\Models\Provider::class,
            'module' => 'servicehub',
            'label' => 'Provider coverage',
            'hint' => 'The areas this provider will travel to.',
        ],
        'fulfillment-shipment' => [
            'model' => \Modules\Fulfillment\Models\Shipment::class,
            'module' => 'fulfillment',
            'label' => 'Delivery area',
            'hint' => 'Where this shipment is going.',
        ],
        'organization' => [
            'model' => \App\Models\Organization::class,
            'module' => 'settings',
            'label' => 'Trading areas',
            'hint' => 'Where this organization operates.',
        ],
    ],

    /*
    | Place search runs through Nominatim, which is free and needs no key.
    |
    | Its usage policy asks for an identifying User-Agent and no more than one
    | request a second, so the lookup is proxied by PlaceSearchController rather
    | than called from the browser: a server can honour both, a page with a
    | keystroke handler cannot.
    */
    'geocoder' => [
        'endpoint' => env('ZONES_GEOCODER_URL', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env('ZONES_GEOCODER_UA', 'FGE-Admin/1.0 (+https://fge.or.tz)'),
        'cache_minutes' => 1440,
        'country_codes' => env('ZONES_GEOCODER_COUNTRIES', 'tz'),
    ],
];
