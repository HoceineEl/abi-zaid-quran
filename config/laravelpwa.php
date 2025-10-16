<?php

return [
    'name' => 'LaravelPWA',
    'manifest' => [
        'name' => env('APP_NAME', 'My PWA App'),
        'short_name' => 'جمعية أبن أبي زيد القيرواني',
        'start_url' => '/',
        'background_color' => '#ffffff',
        'theme_color' => '#000000',
        'display' => 'standalone',
        'orientation' => 'any',
        'status_bar' => 'black',
        'icons' => [
            '72x72' => [
                'path' => '/images/icons/icon-72x72.png',
                'purpose' => 'any',
            ],
            '96x96' => [
                'path' => '/images/icons/icon-96x96.png',
                'purpose' => 'any',
            ],
            '128x128' => [
                'path' => '/images/icons/icon-128x128.png',
                'purpose' => 'any',
            ],
            '144x144' => [
                'path' => '/images/icons/icon-144x144.png',
                'purpose' => 'any',
            ],
            '152x152' => [
                'path' => '/images/icons/icon-152x152.png',
                'purpose' => 'any',
            ],
            '192x192' => [
                'path' => '/images/icons/icon-192x192.png',
                'purpose' => 'any',
            ],
            '384x384' => [
                'path' => '/images/icons/icon-384x384.png',
                'purpose' => 'any',
            ],
            '512x512' => [
                'path' => '/images/icons/icon-512x512.png',
                'purpose' => 'any',
            ],
            '71x71' => [
                'path' => '/images/windows11/SmallTile.scale-100.png',
                'purpose' => 'any',
            ],
            '89x89' => [
                'path' => '/images/windows11/SmallTile.scale-125.png',
                'purpose' => 'any',
            ],
            '107x107' => [
                'path' => '/images/windows11/SmallTile.scale-150.png',
                'purpose' => 'any',
            ],
            '142x142' => [
                'path' => '/images/windows11/SmallTile.scale-200.png',
                'purpose' => 'any',
            ],
            '284x284' => [
                'path' => '/images/windows11/SmallTile.scale-400.png',
                'purpose' => 'any',
            ],
            '150x150' => [
                'path' => '/images/windows11/Square150x150Logo.scale-100.png',
                'purpose' => 'any',
            ],
            '188x188' => [
                'path' => '/images/windows11/Square150x150Logo.scale-125.png',
                'purpose' => 'any',
            ],
            '225x225' => [
                'path' => '/images/windows11/Square150x150Logo.scale-150.png',
                'purpose' => 'any',
            ],
            '300x300' => [
                'path' => '/images/windows11/Square150x150Logo.scale-200.png',
                'purpose' => 'any',
            ],
            '600x600' => [
                'path' => '/images/windows11/Square150x150Logo.scale-400.png',
                'purpose' => 'any',
            ],
            '310x150' => [
                'path' => '/images/windows11/Wide310x150Logo.scale-100.png',
                'purpose' => 'any',
            ],
            '388x188' => [
                'path' => '/images/windows11/Wide310x150Logo.scale-125.png',
                'purpose' => 'any',
            ],
            '465x225' => [
                'path' => '/images/windows11/Wide310x150Logo.scale-150.png',
                'purpose' => 'any',
            ],
            '620x300' => [
                'path' => '/images/windows11/Wide310x150Logo.scale-200.png',
                'purpose' => 'any',
            ],
            '1240x600' => [
                'path' => '/images/windows11/Wide310x150Logo.scale-400.png',
                'purpose' => 'any',
            ],
            '310x310' => [
                'path' => '/images/windows11/LargeTile.scale-100.png',
                'purpose' => 'any',
            ],
            '388x388' => [
                'path' => '/images/windows11/LargeTile.scale-125.png',
                'purpose' => 'any',
            ],
            '465x465' => [
                'path' => '/images/windows11/LargeTile.scale-150.png',
                'purpose' => 'any',
            ],
            '620x620' => [
                'path' => '/images/windows11/LargeTile.scale-200.png',
                'purpose' => 'any',
            ],
            '1240x1240' => [
                'path' => '/images/windows11/LargeTile.scale-400.png',
                'purpose' => 'any',
            ],
            '44x44' => [
                'path' => '/images/windows11/Square44x44Logo.scale-100.png',
                'purpose' => 'any',
            ],
            '55x55' => [
                'path' => '/images/windows11/Square44x44Logo.scale-125.png',
                'purpose' => 'any',
            ],
            '66x66' => [
                'path' => '/images/windows11/Square44x44Logo.scale-150.png',
                'purpose' => 'any',
            ],
            '88x88' => [
                'path' => '/images/windows11/Square44x44Logo.scale-200.png',
                'purpose' => 'any',
            ],
            '176x176' => [
                'path' => '/images/windows11/Square44x44Logo.scale-400.png',
                'purpose' => 'any',
            ],
            '50x50' => [
                'path' => '/images/windows11/StoreLogo.scale-100.png',
                'purpose' => 'any',
            ],
            '63x63' => [
                'path' => '/images/windows11/StoreLogo.scale-125.png',
                'purpose' => 'any',
            ],
            '75x75' => [
                'path' => '/images/windows11/StoreLogo.scale-150.png',
                'purpose' => 'any',
            ],
            '100x100' => [
                'path' => '/images/windows11/StoreLogo.scale-200.png',
                'purpose' => 'any',
            ],
            '200x200' => [
                'path' => '/images/windows11/StoreLogo.scale-400.png',
                'purpose' => 'any',
            ],
            '620x300' => [
                'path' => '/images/windows11/SplashScreen.scale-100.png',
                'purpose' => 'any',
            ],
            '775x375' => [
                'path' => '/images/windows11/SplashScreen.scale-125.png',
                'purpose' => 'any',
            ],
            '930x450' => [
                'path' => '/images/windows11/SplashScreen.scale-150.png',
                'purpose' => 'any',
            ],
            '1240x600' => [
                'path' => '/images/windows11/SplashScreen.scale-200.png',
                'purpose' => 'any',
            ],
            '2480x1200' => [
                'path' => '/images/windows11/SplashScreen.scale-400.png',
                'purpose' => 'any',
            ],
            '16x16' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-16.png',
                'purpose' => 'any',
            ],
            '20x20' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-20.png',
                'purpose' => 'any',
            ],
            '24x24' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-24.png',
                'purpose' => 'any',
            ],
            '30x30' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-30.png',
                'purpose' => 'any',
            ],
            '32x32' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-32.png',
                'purpose' => 'any',
            ],
            '36x36' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-36.png',
                'purpose' => 'any',
            ],
            '40x40' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-40.png',
                'purpose' => 'any',
            ],
            '48x48' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-48.png',
                'purpose' => 'any',
            ],
            '60x60' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-60.png',
                'purpose' => 'any',
            ],
            '64x64' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-64.png',
                'purpose' => 'any',
            ],
            '72x72' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-72.png',
                'purpose' => 'any',
            ],
            '80x80' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-80.png',
                'purpose' => 'any',
            ],
            '96x96' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-96.png',
                'purpose' => 'any',
            ],
            '256x256' => [
                'path' => '/images/windows11/Square44x44Logo.targetsize-256.png',
                'purpose' => 'any',
            ],
            '16x16' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-16.png',
                'purpose' => 'any',
            ],
            '20x20' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-20.png',
                'purpose' => 'any',
            ],
            '24x24' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-24.png',
                'purpose' => 'any',
            ],
            '30x30' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-30.png',
                'purpose' => 'any',
            ],
            '32x32' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-32.png',
                'purpose' => 'any',
            ],
            '36x36' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-36.png',
                'purpose' => 'any',
            ],
            '40x40' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-40.png',
                'purpose' => 'any',
            ],
            '44x44' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-44.png',
                'purpose' => 'any',
            ],
            '48x48' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-48.png',
                'purpose' => 'any',
            ],
            '60x60' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-60.png',
                'purpose' => 'any',
            ],
            '64x64' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-64.png',
                'purpose' => 'any',
            ],
            '72x72' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-72.png',
                'purpose' => 'any',
            ],
            '80x80' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-80.png',
                'purpose' => 'any',
            ],
            '96x96' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-96.png',
                'purpose' => 'any',
            ],
            '256x256' => [
                'path' => '/images/windows11/Square44x44Logo.altform-unplated_targetsize-256.png',
                'purpose' => 'any',
            ],
            '16x16' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-16.png',
                'purpose' => 'any',
            ],
            '20x20' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-20.png',
                'purpose' => 'any',
            ],
            '24x24' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-24.png',
                'purpose' => 'any',
            ],
            '30x30' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-30.png',
                'purpose' => 'any',
            ],
            '32x32' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-32.png',
                'purpose' => 'any',
            ],
            '36x36' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-36.png',
                'purpose' => 'any',
            ],
            '40x40' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-40.png',
                'purpose' => 'any',
            ],
            '44x44' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-44.png',
                'purpose' => 'any',
            ],
            '48x48' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-48.png',
                'purpose' => 'any',
            ],
            '60x60' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-60.png',
                'purpose' => 'any',
            ],
            '64x64' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-64.png',
                'purpose' => 'any',
            ],
            '72x72' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-72.png',
                'purpose' => 'any',
            ],
            '80x80' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-80.png',
                'purpose' => 'any',
            ],
            '96x96' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-96.png',
                'purpose' => 'any',
            ],
            '256x256' => [
                'path' => '/images/windows11/Square44x44Logo.altform-lightunplated_targetsize-256.png',
                'purpose' => 'any',
            ],
            '512x512' => [
                'path' => '/images/android/android-launchericon-512-512.png',
                'purpose' => 'any',
            ],
            '192x192' => [
                'path' => '/images/android/android-launchericon-192-192.png',
                'purpose' => 'any',
            ],
            '144x144' => [
                'path' => '/images/android/android-launchericon-144-144.png',
                'purpose' => 'any',
            ],
            '96x96' => [
                'path' => '/images/android/android-launchericon-96-96.png',
                'purpose' => 'any',
            ],
            '72x72' => [
                'path' => '/images/android/android-launchericon-72-72.png',
                'purpose' => 'any',
            ],
            '48x48' => [
                'path' => '/images/android/android-launchericon-48-48.png',
                'purpose' => 'any',
            ],
            '16x16' => [
                'path' => '/images/ios/16.png',
                'purpose' => 'any',
            ],
            '20x20' => [
                'path' => '/images/ios/20.png',
                'purpose' => 'any',
            ],
            '29x29' => [
                'path' => '/images/ios/29.png',
                'purpose' => 'any',
            ],
            '32x32' => [
                'path' => '/images/ios/32.png',
                'purpose' => 'any',
            ],
            '40x40' => [
                'path' => '/images/ios/40.png',
                'purpose' => 'any',
            ],
            '50x50' => [
                'path' => '/images/ios/50.png',
                'purpose' => 'any',
            ],
            '57x57' => [
                'path' => '/images/ios/57.png',
                'purpose' => 'any',
            ],
            '58x58' => [
                'path' => '/images/ios/58.png',
                'purpose' => 'any',
            ],
            '60x60' => [
                'path' => '/images/ios/60.png',
                'purpose' => 'any',
            ],
            '64x64' => [
                'path' => '/images/ios/64.png',
                'purpose' => 'any',
            ],
            '72x72' => [
                'path' => '/images/ios/72.png',
                'purpose' => 'any',
            ],
            '76x76' => [
                'path' => '/images/ios/76.png',
                'purpose' => 'any',
            ],
            '80x80' => [
                'path' => '/images/ios/80.png',
                'purpose' => 'any',
            ],
            '87x87' => [
                'path' => '/images/ios/87.png',
                'purpose' => 'any',
            ],
            '100x100' => [
                'path' => '/images/ios/100.png',
                'purpose' => 'any',
            ],
            '114x114' => [
                'path' => '/images/ios/114.png',
                'purpose' => 'any',
            ],
            '120x120' => [
                'path' => '/images/ios/120.png',
                'purpose' => 'any',
            ],
            '128x128' => [
                'path' => '/images/ios/128.png',
                'purpose' => 'any',
            ],
            '144x144' => [
                'path' => '/images/ios/144.png',
                'purpose' => 'any',
            ],
            '152x152' => [
                'path' => '/images/ios/152.png',
                'purpose' => 'any',
            ],
            '167x167' => [
                'path' => '/images/ios/167.png',
                'purpose' => 'any',
            ],
            '180x180' => [
                'path' => '/images/ios/180.png',
                'purpose' => 'any',
            ],
            '192x192' => [
                'path' => '/images/ios/192.png',
                'purpose' => 'any',
            ],
            '256x256' => [
                'path' => '/images/ios/256.png',
                'purpose' => 'any',
            ],
            '512x512' => [
                'path' => '/images/ios/512.png',
                'purpose' => 'any',
            ],
            '1024x1024' => [
                'path' => '/images/ios/1024.png',
                'purpose' => 'any',
            ],
        ],
        'splash' => [
            '640x1136' => '/images/windows11/SplashScreen.scale-400.png',
            '750x1334' => '/images/windows11/SplashScreen.scale-400.png',
            '828x1792' => '/images/windows11/SplashScreen.scale-400.png',
            '1125x2436' => '/images/windows11/SplashScreen.scale-400.png',
            '1242x2208' => '/images/windows11/SplashScreen.scale-400.png',
            '1242x2688' => '/images/windows11/SplashScreen.scale-400.png',
            '1536x2048' => '/images/windows11/SplashScreen.scale-400.png',
            '1668x2224' => '/images/windows11/SplashScreen.scale-400.png',
            '1668x2388' => '/images/windows11/SplashScreen.scale-400.png',
            '2048x2732' => '/images/windows11/SplashScreen.scale-400.png',
        ],
        'shortcuts' => [
            [
                'name' => 'جمعية أبن أبي زيد القيرواني',
                'description' => 'app.dashboard_description',
                'url' => '/admin/dashboard',
                'icons' => [
                    'src' => '/images/icons/icon-72x72.png',
                    'purpose' => 'any',
                ],
            ],
        ],
        'custom' => [],
    ],
];
