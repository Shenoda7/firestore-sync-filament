<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firestore Sync Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines how Firestore documents are mapped to MySQL tables.
    | You can define multiple collections and their corresponding table mappings.
    |
    */

    'collections' => [
        'users' => [
            'table' => 'users',
            'model' => \App\Models\User::class,
            'unique_key' => 'email',
            'field_mappings' => [
                'name' => 'name',
                'email' => 'email',
                'age' => 'age',
                // Handle nested fields
                'profile.address.city' => 'city',
                'profile.address.country' => 'country',
                'profile.phone' => 'phone',
                // Arrays (JSON encoded)
                'tags' => 'tags',
                'preferences' => 'preferences',
                // Handle complex objects (will be JSON encoded)
                'metadata' => 'metadata',
            ],
            'transformations' => [
                'name' => 'ucwords', // Transform to title case
                'email' => 'strtolower', // Transform to lowercase
                'age' => 'intval', // Ensure integer
                'tags' => 'json_encode', // JSON encode arrays
                'preferences' => 'json_encode', // JSON encode objects
                'metadata' => 'json_encode', // JSON encode complex objects
            ],
            'defaults' => [
                'password' => 'password', // Default password for new users
            ],
        ],
        
        'products' => [
            'table' => 'products',
            'model' => \App\Models\Product::class,
            'unique_key' => 'sku',
            'field_mappings' => [
                'name' => 'name',
                'sku' => 'sku',
                'price' => 'price',
                'category.name' => 'category_name',
                'category.id' => 'category_id',
                'images' => 'images', // Array of image URLs
                'specifications' => 'specifications', // Complex object
                'variants' => 'variants', // Array of variant objects
            ],
            'transformations' => [
                'name' => 'ucwords',
                'price' => 'floatval',
                'images' => 'json_encode',
                'specifications' => 'json_encode',
                'variants' => 'json_encode',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Settings
    |--------------------------------------------------------------------------
    */

    'batch_size' => 100, // Number of documents to process in each batch
    'timeout' => 300, // Request timeout in seconds
    'retry_attempts' => 3, // Number of retry attempts for failed requests
    
    /*
    |--------------------------------------------------------------------------
    | Field Type Handling
    |--------------------------------------------------------------------------
    |
    | Define how different Firestore field types should be handled
    |
    */

    'field_types' => [
        'stringValue' => 'string',
        'integerValue' => 'integer',
        'doubleValue' => 'float',
        'booleanValue' => 'boolean',
        'arrayValue' => 'array',
        'mapValue' => 'object',
        'timestampValue' => 'datetime',
        'nullValue' => 'null',
    ],
]; 