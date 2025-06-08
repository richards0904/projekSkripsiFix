<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'Admin Jala', // Or any name you prefer
            'email' => 'admin@jala.test', // Change this to your desired admin email
            'password' => Hash::make('password'), // IMPORTANT: Change 'password' to a strong, unique password
            'email_verified_at' => now(),
        ]);
    }
}
