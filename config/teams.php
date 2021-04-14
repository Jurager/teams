<?php

use Jurager\Teams\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of Team's features are optional. You may disable the features
    | by removing them from this array. You're free to only remove some of
    | these features or you can even remove all of these if you need to.
    |
    */

    'features' => [
        // Features::api(),
        Features::teams(['invitations' => true]),
        Features::accountDeletion(),
    ],
];
