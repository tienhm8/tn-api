<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seed cấu hình hệ thống mặc định.
     */
    public function run(): void
    {
        $settings = [
            'reminder_lead_minutes' => '0',  // nhắc đúng giờ scheduled_at
            'last_assigned_sale_id' => null, // con trỏ round-robin gán sale
            'customer_code_seq' => '0',      // counter sinh mã KH (KH000123)
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
