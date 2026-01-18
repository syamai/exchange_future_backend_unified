<?php

namespace Database\Seeders;

use App\Models\ChatbotType;
use Illuminate\Database\Seeder;

class ChatbotTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            [
                'name' => 'Self-service',
                'id' => 1
            ],
            [
                'name' => 'FAQ',
                'id' => 2
            ],
        ];

        foreach ($types as $type) {
            ChatbotType::updateOrCreate(
                ['id' => $type['id']],
                [
                    'name' => $type['name']
                ]
            );
        }
    }
} 