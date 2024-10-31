<?php

namespace Database\Seeders;

use App\Models\Memorizer;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $memorizers = Memorizer::where('exempt', false)->get();
        $months = collect(range(0, 2))->map(fn($monthsAgo) => Carbon::now()->subMonths($monthsAgo));

        foreach ($memorizers as $memorizer) {
            foreach ($months as $month) {
                // 90% chance of payment for non-exempt students
                if (rand(1, 100) <= 90) {
                    Payment::create([
                        'memorizer_id' => $memorizer->id,
                        'amount' => rand(100, 500),
                        'payment_date' => $month->copy()->addDays(rand(1, 28)),
                        'payment_method' => ['cash', 'card', 'transfer'][rand(0, 2)],
                        'notes' => 'Monthly payment for ' . $month->format('F Y'),
                    ]);
                }
            }
        }
    }
}
