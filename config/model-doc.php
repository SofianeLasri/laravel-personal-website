<?php

use Illuminate\Database\Eloquent\Model;

return [
    // Generate PHPDoc properties for relation methods
    'relations' => [
        // @property MyRelatedModel|null $myRelation
        'enabled' => true,

        'counts' => [
            // Enable generating PHPDoc properties for relations count attributes
            // @property int|null $my_relation_counts
            'enabled' => true,
        ],

        // Base model class to be used for MorphTo relation return type
        'base_model' => Model::class,
    ],

    // Generate PHPDoc properties for database columns
    'attributes' => [
        'enabled' => true,

        // Use dbal class type if col type not mapped
        'fallback_type' => false,
    ],

    // Generate properties for model accessors like `getTitleAttribute`
    'accessors' => [
        'enabled' => true,
    ],

    // Generate model query scope methods. Only looks for existing method prefixed with "scope"
    'scopes' => [
        // @method static \Illuminate\Database\Eloquent\Builder whereId(int $id)',
        'enabled' => true,
        // Define certain scope methods that should be ignored (provide final method name without "scope" prefix)
        'ignore' => [
            // 'whereUuid',
        ],
    ],

    // Generate factory related tags
    'factories' => [
        // Add Model::factory() method type hint to Model class
        'enabled' => false,
    ],

    'fail_when_empty' => false,

    // Ignore models by FQCN
    'ignore' => [],

    'custom_tags' => [
        // Add a "@mixin" tag value to support static method linting for IDEs.
        'mixins' => [
            // \Illuminate\Database\Eloquent\Model::class,
        ],
    ],

    // Add generics to class declarations with generics like:
    //   \Illuminate\Database\Eloquent\Builder<\App\Models\User>
    'generics' => true,

    'tag_sorting' => [
        'see', 'author', 'property', 'property-read', 'property-write',
        'method', 'deprecated', 'since', 'version', 'var', 'type', 'param',
        'throws', 'mixin', 'return',
    ],
];
