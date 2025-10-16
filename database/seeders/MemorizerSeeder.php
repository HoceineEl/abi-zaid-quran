<?php

namespace Database\Seeders;

use App\Models\MemoGroup;
use App\Models\Memorizer;
use Illuminate\Database\Seeder;

class MemorizerSeeder extends Seeder
{
    public function run(): void
    {
        $groups = MemoGroup::all();

        foreach ($groups as $group) {
            Memorizer::factory()
                ->count(rand(5, 15))
                ->create([
                    'memo_group_id' => $group->id,
                    'exempt' => (bool) rand(0, 5), // 20% chance of being exempt
                ]);
        }
    }
}
