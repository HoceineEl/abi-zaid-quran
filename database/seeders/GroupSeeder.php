<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\MemoGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        // Create Quran Program Groups
        $managers = User::where('role', '!=', 'admin')->get();
        foreach ($managers as $manager) {
            $groups = Group::factory()
                ->count(rand(1, 3))
                ->create();

            foreach ($groups as $group) {
                $group->managers()->attach($manager->id);
            }
        }

        // Create Association Groups (MemoGroups)
        MemoGroup::factory()
            ->count(5)
            ->create([
                'price' => rand(50, 150),
            ]);
    }
}
