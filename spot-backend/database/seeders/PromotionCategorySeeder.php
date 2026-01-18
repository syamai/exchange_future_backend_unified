<?php

namespace Database\Seeders;

use App\Enums\PromotionCategoryStatus;
use App\Models\PromotionCategory;
use Illuminate\Database\Seeder;

class PromotionCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => [
                    'en' => 'Futures',
                    'vi' => 'Futures'
                ],
                'key' => 'futures'
            ],
            [
                'name' => [
                    'en' => 'Spot',
                    'vi' => 'Spot'
                ],
                'key' => 'spot'
            ],
            [
                'name' => [
                    'en' => 'New User Exclusive',
                    'vi' => 'Ưu đãi người dùng mới'
                ],
                'key' => 'new-user-exclusive'
            ],
            [
                'name' => [
                    'en' => 'Airdrop',
                    'vi' => 'Airdrop'
                ],
                'key' => 'airdrop'
            ]
        ];

        foreach ($categories as $category) {
            PromotionCategory::updateOrCreate(
                ['key' => $category['key']],
                [
                    'name' => json_encode($category['name']),
                    'status' => PromotionCategoryStatus::ACTIVE
                ]
            );
        }
    }
} 