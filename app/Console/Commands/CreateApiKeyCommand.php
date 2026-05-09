<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class CreateApiKeyCommand extends Command
{
    protected $signature = 'cas:create-api-key {name : Human-readable client name}';

    protected $description = 'Create a new API key and print the plaintext once';

    public function handle(): int
    {
        $plaintext = 'webte2_'.Str::random(48);

        /** @var string $name */
        $name = $this->argument('name');

        ApiKey::create([
            'name' => $name,
            'key_hash' => $plaintext,  // observer hashes on creating
        ]);

        $this->info("API key created: {$plaintext}");
        $this->warn('Store this key now — it will not be shown again.');

        return self::SUCCESS;
    }
}
