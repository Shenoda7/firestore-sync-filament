<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class TestMySQLData extends Command
{
    protected $signature = 'mysql:test';
    protected $description = 'Test MySQL data after Firestore sync';

    public function handle()
    {
        $this->info('Testing MySQL data...');
        
        $users = User::all();
        
        $this->info("Found " . $users->count() . " users in MySQL:");
        
        foreach ($users as $user) {
            $this->line("  - {$user->name} ({$user->email}) - Age: {$user->age}");
        }
        
        $this->info('✅ MySQL data test completed!');
        
        return 0;
    }
} 