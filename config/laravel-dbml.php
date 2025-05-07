<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Ignore Behavior
    |--------------------------------------------------------------------------
    |
    | By default, the package will ignore certain tables. Set to false to include all tables.
    |
    */
    'ignore_by_default' => true,
    
    /*
    |--------------------------------------------------------------------------
    | Ignore Presets
    |--------------------------------------------------------------------------
    |
    | Predefined groups of tables to ignore
    |
    */
    'ignore_presets' => [
        'system' => [
            'migrations',
            'failed_jobs',
            'password_resets',
            'personal_access_tokens',
            'sessions',
            'cache',
            'jobs',
        ],
        'spatie-permissions' => [
            'permissions',
            'roles',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
        ],
        'telescope' => [
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Active Ignore Presets
    |--------------------------------------------------------------------------
    |
    | The active presets that will be used by default
    |
    */
    'active_presets' => ['system'],

    /*
    |--------------------------------------------------------------------------
    | Additional Tables to Ignore
    |--------------------------------------------------------------------------
    |
    | Add any additional tables you want to ignore here.
    |
    */
    'ignored_tables' => [
        // Add custom tables to ignore
    ],

    /*
    |--------------------------------------------------------------------------
    | Models Directory
    |--------------------------------------------------------------------------
    |
    | The directory where your Laravel models are located.
    | Used for detecting casts.
    |
    */
    'models_dir' => app_path('Models'),

    /*
    |--------------------------------------------------------------------------
    | Cast Documentation
    |--------------------------------------------------------------------------
    |
    | Configuration for documenting casted attributes
    |
    */
    'document_casts' => true,
    
    /*
    |--------------------------------------------------------------------------
    | Cast Types to Document
    |--------------------------------------------------------------------------
    |
    | These cast types will be documented with their structure.
    |
    */
    'document_cast_types' => [
        'json',
        'array',
        'object',
        'collection',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Column-level Schema Documentation
    |--------------------------------------------------------------------------
    |
    | When enabled, schema information will be displayed directly in the column
    | definition instead of in a separate Note block.
    |
    */
    'inline_schema' => true,
    
    /*
    |--------------------------------------------------------------------------
    | Support for Spatie Data Objects
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will analyze Spatie Data objects to extract
    | their structure for documentation.
    |
    */
    'document_spatie_data' => true,
    
    /*
    |--------------------------------------------------------------------------
    | Spatie Data Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace under which your Spatie Data objects are defined.
    |
    */
    'spatie_data_namespace' => 'App\\ValueObjects',
    
    /*
    |--------------------------------------------------------------------------
    | Only Tables
    |--------------------------------------------------------------------------
    |
    | Only parse the specified tables. This can also be set via command line.
    |
    */
    'only_tables' => [],
];
