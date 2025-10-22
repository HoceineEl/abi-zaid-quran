<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Student Disconnection Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for student disconnection tracking and management.
    |
    */

    'disconnection' => [
        /*
         * Minimum number of consecutive absent days (without reason) required
         * for a student to be considered disconnected from an active group.
         */
        'consecutive_absent_days_threshold' => env('DISCONNECTION_THRESHOLD', 3),
    ],

];
