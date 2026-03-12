<?php

// config/cors.php
// ─────────────────────────────────────────────────────────────────
// Configuration CORS EcoDash — accepte toutes les origines
// (dashboard HTML servi depuis la même app + apps mobiles Flutter)
// ─────────────────────────────────────────────────────────────────

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
