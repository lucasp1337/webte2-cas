<?php

declare(strict_types=1);

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Your API path. By default, all routes starting with this path will be added to the docs.
     * Restricting to api/v1 ensures Horizon, Telescope, and debug routes are excluded.
     */
    'api_path' => 'api/v1',

    /*
     * Your API domain. By default, app domain is used.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    'info' => [
        /*
         * API version.
         */
        'version' => '1.0.0',

        /*
         * Description rendered on the home page of the API documentation.
         */
        'description' => 'Sandboxed GNU Octave bridge: execute commands, run dynamic-system simulations, manage sessions, and export logs.',
    ],

    /*
     * Customize Stoplight Elements UI
     */
    'ui' => [
        /*
         * Define the title of the documentation's website.
         */
        'title' => 'WEBTE2 — REST API',

        /*
         * Define the theme of the documentation. Available options are `light`, `dark`, and `system`.
         */
        'theme' => 'light',

        /*
         * Hide the `Try It` feature. Enabled by default.
         */
        'hide_try_it' => false,

        /*
         * Hide the schemas in the Table of Contents. Enabled by default.
         */
        'hide_schemas' => false,

        /*
         * URL to an image that displays as a small square logo next to the title, above the table of contents.
         */
        'logo' => '',

        /*
         * Use to fetch the credential policy for the Try It feature.
         */
        'try_it_credentials_policy' => 'include',

        /*
         * Layout for Elements: sidebar, responsive, or stacked.
         */
        'layout' => 'responsive',
    ],

    /*
     * The list of servers of the API.
     */
    'servers' => null,

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When true, Scramble flattens deep objects in query parameters.
     */
    'flatten_deep_query_parameters' => true,

    /*
     * The spec endpoint is intentionally public — no auth required so that
     * Swagger UI (and curl) can fetch it without X-API-Key.
     */
    'middleware' => [
        'api',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];
