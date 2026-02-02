<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Seed Users
        $this->call('UserSeeder');
        
        // Seed Products
        $this->call('ProductSeeder');
    }
}