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

    /*
    |--------------------------------------------------------------------------
    | Simulated scans
    |--------------------------------------------------------------------------
    |
    | The "Simulate seeded scan" buttons pick a random seeded pallet. They are
    | always on in local and testing; set FREIGHT_SIMULATE_SCANS=true to turn
    | them on for a demo deployment. Leave it off on a live site.
    |
    */

    'simulate_scans' => (bool) env('FREIGHT_SIMULATE_SCANS', false),

];
