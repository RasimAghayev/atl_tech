<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CallEventSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔐 Generating Call Event API Token...');

        // Generate secure random token
        $token = 'ce_' . Str::random(40);

        // Update .env file
        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        // Check if CALL_EVENT_API_TOKEN already exists
        if (str_contains($envContent, 'CALL_EVENT_API_TOKEN=')) {
            $envContent = preg_replace(
                '/CALL_EVENT_API_TOKEN=.*/',
                'CALL_EVENT_API_TOKEN=' . $token,
                $envContent
            );
        } else {
            $envContent .= "\n# Call Event API Token (Auto-generated)\nCALL_EVENT_API_TOKEN={$token}\n";
        }

        File::put($envPath, $envContent);

        $this->command->line('');
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('API Token generated successfully!');
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->line('');
        $this->command->line('Your API Token:');
        $this->command->line('');
        $this->command->line('   ' . $token);
        $this->command->line('');
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->line('');
        $this->command->line('Usage Example:');
        $this->command->line('');
        $this->command->line('   curl -X POST http://localhost/api/v1/call-events \\');
        $this->command->line('     -H "Content-Type: application/json" \\');
        $this->command->line('     -H "Authorization: Bearer ' . $token . '" \\');
        $this->command->line('     -d \'{"call_id":"CALL-001","caller_number":"+994501234567",...}\'');
        $this->command->line('');
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->line('');
        $this->command->warn('Keep this token secure! It has been saved to your .env file.');
        $this->command->line('');
    }
}