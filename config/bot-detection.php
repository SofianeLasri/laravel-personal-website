<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Suspicious Referers
    |--------------------------------------------------------------------------
    |
    | This array contains terms that, when found in the HTTP referer header,
    | will classify the request as coming from a bot. The matching is
    | case-insensitive and checks if the referer contains the term.
    |
    */
    'suspicious_referers' => [
        'myworkdayjobs',
    ],
];
