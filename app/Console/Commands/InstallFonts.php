<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallFonts extends Command
{
    protected $signature = 'fonts:install';

    protected $description = 'Install required fonts for PDF generation';

    public function handle()
    {
        $fontsPath = storage_path('fonts');

        if (! file_exists($fontsPath)) {
            mkdir($fontsPath, 0755, true);
        }

        $fonts = [
            'Cairo-Regular.ttf' => 'https://fonts.gstatic.com/s/cairo/v28/SLXgc1nY6HkvangtZmpQdkhzfH5lkSs2SgRjCAGMQ1z0hGA-W1Q.ttf',
            'Cairo-Bold.ttf' => 'https://fonts.gstatic.com/s/cairo/v28/SLXgc1nY6HkvangtZmpQdkhzfH5lkSs2SgRjCAGMQ1z0hL4-W1Q.ttf',
        ];

        foreach ($fonts as $fontName => $url) {
            $fontPath = $fontsPath.'/'.$fontName;

            if (! file_exists($fontPath)) {
                $this->info("Downloading {$fontName}...");
                $fontContent = file_get_contents($url);
                file_put_contents($fontPath, $fontContent);
                $this->info("{$fontName} installed successfully.");
            } else {
                $this->info("{$fontName} already exists.");
            }
        }

        $this->info('All fonts installed successfully!');
    }
}
