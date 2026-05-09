<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Seeder;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        /** @var string|null $plaintext */
        $plaintext = config('cas.api_key_plaintext');

        if ($plaintext === null || $plaintext === '') {
            $this->command->warn('CAS_API_KEY_PLAINTEXT is not set — skipping demo API key creation.');

            return;
        }

        $prefix = substr($plaintext, 0, 8);

        if (ApiKey::query()->where('key_prefix', $prefix)->exists()) {
            $this->command->info('Demo API key already exists — skipping.');

            return;
        }

        // The ApiKeyObserver hashes key_hash on creating.
        ApiKey::create([
            'name' => 'Demo key',
            'key_hash' => $plaintext,
        ]);

        $this->command->info('Demo API key created successfully.');
    }
}
