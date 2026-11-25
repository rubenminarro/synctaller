<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'code' => 'general',
                'name' => 'General',
            ],
            [
                'code' => 'mechanical',
                'name' => 'Mecánica',
            ],
            [
                'code' => 'bodywork',
                'name' => 'Chapería',
            ],
            [
                'code' => 'electrical',
                'name' => 'Electricidad',
            ],
            [
                'code' => 'tires',
                'name' => 'Neumáticos',
            ],
        ];

        foreach ($categories as $category) {
            ServiceCategory::updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name']]
            );
        }

    }
}
