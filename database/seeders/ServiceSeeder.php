<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Seed 7 dịch vụ tiêu chuẩn của Tiến Nhân.
     */
    public function run(): void
    {
        $services = [
            ['code' => 'trademark_protection', 'name' => 'Đăng ký bảo hộ thương hiệu'],
            ['code' => 'growing_area_code', 'name' => 'Đăng ký mã vùng trồng'],
            ['code' => 'iso_9001_2015', 'name' => 'ISO 9001:2015'],
            ['code' => 'haccp', 'name' => 'HACCP'],
            ['code' => 'iso_22000_2018', 'name' => 'ISO 22000:2018'],
            ['code' => 'gacc_code', 'name' => 'Đăng ký mã GACC'],
            ['code' => 'iso_14001_2015', 'name' => 'ISO 14001:2015'],
        ];

        foreach ($services as $index => $service) {
            Service::updateOrCreate(
                ['code' => $service['code']],
                [
                    'name' => $service['name'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
