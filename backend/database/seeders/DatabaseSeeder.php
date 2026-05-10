<?php

namespace Database\Seeders;

use App\Models\BarangaySetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        BarangaySetting::query()->firstOrCreate([], [
            'barangay_name' => 'Barangay Sample',
            'contact_email' => 'admin@barangay.gov.ph',
            'contact_number' => '(02) 8888-8888',
            'notify_email_new_complaints' => true,
            'notify_sms_urgent_cases' => true,
            'notify_daily_summary_reports' => false,
        ]);

        User::query()->updateOrCreate(
            ['email' => 'admin@barangay.com'],
            [
                'name' => 'Maria Santos',
                'contact_number' => '09171234567',
                'role' => 'admin',
                'password' => Hash::make('admin123'),
            ]
        );
    }
}
