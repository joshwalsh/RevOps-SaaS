<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Only the public tracking endpoints need this. They're called by
    | JavaScript embedded on tenants' own websites, which are arbitrary
    | domains we don't know in advance, so the origin is left wide open.
    | These endpoints never use cookies (the client passes anon_id itself),
    | so supports_credentials stays false.
    |
    */

    'paths' => ['api/identity/*'],

    'allowed_methods' => ['POST'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
