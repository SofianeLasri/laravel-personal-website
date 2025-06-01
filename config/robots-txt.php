<?php

return [
    'environments' => [
        'production' => [
            'paths' => [
                '*' => [
                    'disallow' => [
                        '/dashboard/',
                        '/login',
                        '/register',
                        '/password/',
                        '/email/',
                    ],
                    'allow' => [],
                ],
            ],
            'sitemaps' => [
                '/sitemap.xml',
            ],
        ],
        'local' => [
            'paths' => [
                '*' => [
                    'disallow' => [
                        '/dashboard/',
                        '/login',
                        '/register',
                        '/password/',
                        '/email/',
                    ],
                    'allow' => [],
                ],
            ],
            'sitemaps' => [
                '/sitemap.xml',
            ],
        ],
    ],
];
