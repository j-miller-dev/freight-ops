<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Home depot
    |--------------------------------------------------------------------------
    |
    | Everyone works out of one depot for now, so the bays that runners direct
    | freight to are looked up under this depot code.
    |
    */

    'home_depot_code' => env('FREIGHT_HOME_DEPOT', 'MEL'),

];
