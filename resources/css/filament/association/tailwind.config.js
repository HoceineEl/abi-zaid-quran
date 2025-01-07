import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/Association/**/*.php',
        './resources/views/filament/association/**/*.blade.php',
        './resources/views/livewire/association/**/*.blade.php',
        './resources/views/badges/association/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './app/**/*.php',
    ],
}
