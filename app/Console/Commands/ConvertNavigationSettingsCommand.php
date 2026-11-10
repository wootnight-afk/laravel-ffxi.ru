<?php

namespace App\Console\Commands;

use App\Services\NavigationSettingsConverter;
use Illuminate\Console\Command;
use Throwable;

class ConvertNavigationSettingsCommand extends Command
{
    protected $signature = 'app:navigation:convert
                            {--apply : Apply the reviewed conversion}
                            {--settings-sha256= : Expected settings snapshot checksum}
                            {--pages-sha256= : Expected pages snapshot checksum}';

    protected $description = 'Preview or safely convert legacy CMS navigation bindings.';

    public function handle(NavigationSettingsConverter $converter): int
    {
        try {
            $preview = $converter->preview();
            $snapshot = $preview['snapshot'];
            $this->line('Settings SHA256: '.$snapshot['settings_sha256']);
            $this->line('Pages SHA256: '.$snapshot['pages_sha256']);
            $this->line(json_encode([
                'menus' => $preview['menus'],
                'page_flags' => $preview['page_flags'],
                'conflicts' => $preview['conflicts'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            if ($preview['conflicts'] !== []) {
                $this->error('Conversion refused: resolve conflicts explicitly.');

                return self::FAILURE;
            }
            if (! $this->option('apply')) {
                $this->info('Preview only. No data was changed.');

                return self::SUCCESS;
            }
            foreach (['settings', 'pages'] as $name) {
                $expected = $this->option($name.'-sha256');
                if (! is_string($expected) || $expected !== $snapshot[$name.'_sha256']) {
                    $this->error('Missing or changed reviewed checksum: '.$name);

                    return self::FAILURE;
                }
            }
            $result = $converter->apply($preview);
            $this->info($result['changed'] ? 'Converted. Snapshot: '.$result['snapshot_path'] : 'Already converted. No changes.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
