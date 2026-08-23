<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Ivan Padilla Molinares',
            'email' => 'ivanpadillamol@gmail.com',
            'password' => Hash::make('nilson123'),
            'regional_id' => 1,
        ])->assignRole('admin');
    }
}
