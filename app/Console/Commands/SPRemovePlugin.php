<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SPRemovePlugin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *   php artisan sp:remove-plugin SaaSPHP/Blog
     *
     * where "SaaSPHP/Blog" is the vendor and plugin name.
     */
    protected $signature = 'sp:remove-plugin {package : Plugin identifier in Vendor/Plugin format}';

    /**
     * The console command description.
     */
    protected $description = 'Remove a Filament (or legacy) plugin and clean up related entries and migrations.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $package = $this->argument('package');
        if (! Str::contains($package, '/')) {
            $this->error('Please provide the plugin in Vendor/Plugin format.');

            return;
        }

        [$vendor, $plugin] = explode('/', $package, 2);
        $vendor = Str::studly($vendor);
        $plugin = Str::studly($plugin);

        $basePath = config('filament.plugin_path', base_path('app/Plugins'));
        $pluginPath = "{$basePath}/{$vendor}/{$plugin}";

        if (! File::exists($pluginPath)) {
            $this->error("Plugin [{$vendor}/{$plugin}] not found at path {$pluginPath}.");

            return;
        }

        // Offer to rollback migrations if they exist
        $migrationDir = "{$pluginPath}/database/migrations";
        if (File::isDirectory($migrationDir) && count(File::files($migrationDir)) > 0) {
            if ($this->confirm("Migrations found for {$vendor}/{$plugin}. Rollback before removal?")) {
                $this->call('migrate:rollback', [
                    '--path' => $migrationDir,
                    '--realpath' => true,
                ]);
                $this->info("Rolled back migrations in: {$migrationDir}");
            } else {
                $this->warn('Skipping migration rollback.');
            }
        }

        if (! $this->confirm("Are you sure you want to remove the plugin [{$vendor}/{$plugin}] and all its files?")) {
            $this->info('Operation cancelled.');

            return;
        }

        try {
            // Remove plugin directory
            File::deleteDirectory($pluginPath);
            $this->info("Deleted plugin directory: {$pluginPath}");

            // Cleanup composer.json entries
            $this->cleanRootComposerJson($vendor, $plugin, $pluginPath);

            // Regenerate autoload and clear caches
            exec('composer dump-autoload');
            $this->call('optimize:clear');

            $this->info("✅ Successfully removed plugin {$vendor}/{$plugin}.");
        } catch (\Exception $e) {
            $this->error('❌ Error removing plugin: '.$e->getMessage());
        }
    }

    /**
     * Remove plugin-related entries from the root composer.json
     */
    protected function cleanRootComposerJson(string $vendor, string $plugin, string $pluginPath): void
    {
        $composerFile = base_path('composer.json');
        if (! File::exists($composerFile)) {
            $this->warn('composer.json not found, skipping composer cleanup.');

            return;
        }

        $json = File::get($composerFile);
        $data = json_decode($json, true);
        if (! is_array($data)) {
            $this->warn('Invalid composer.json format, skipping cleanup.');

            return;
        }

        $packageName = Str::lower("{$vendor}/{$plugin}");

        // Remove require entry
        if (isset($data['require'][$packageName])) {
            unset($data['require'][$packageName]);
            $this->info("Removed require entry: {$packageName}");
        }

        // Remove path repository
        if (! empty($data['repositories']) && is_array($data['repositories'])) {
            $data['repositories'] = array_filter($data['repositories'], function ($repo) use ($pluginPath) {
                return ! (isset($repo['type'], $repo['url'])
                    && $repo['type'] === 'path'
                    && Str::endsWith(trim($repo['url'], '/'), trim(str_replace(base_path().'/', '', $pluginPath), '/')));
            });
            $this->info('Cleaned path repository entries.');
        }

        // Remove legacy psr-4 autoload entry
        $autoloadKey = "App\\Plugins\\{$plugin}\\";
        if (isset($data['autoload']['psr-4'][$autoloadKey])) {
            unset($data['autoload']['psr-4'][$autoloadKey]);
            $this->info("Removed PSR-4 autoload entry for {$autoloadKey}");
        }

        // Remove provider registration in extra.laravel.providers
        if (! empty($data['extra']['laravel']['providers']) && is_array($data['extra']['laravel']['providers'])) {
            $providerClass = "App\\Plugins\\{$plugin}\\{$plugin}ServiceProvider";
            $data['extra']['laravel']['providers'] = array_filter(
                $data['extra']['laravel']['providers'],
                function ($p) use ($providerClass) {
                    return trim($p, '"') !== $providerClass;
                }
            );
            $this->info("Removed provider registration: {$providerClass}");
        }

        File::put($composerFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
