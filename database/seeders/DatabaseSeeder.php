<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\RFQ;
use App\Models\RFQQuote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->prepareDemoFiles();

        $suppliers = Supplier::factory()
            ->count(12)
            ->create();

        $statusDistribution = [
            'awaiting' => 8,
            'open' => 12,
            'closed' => 8,
            'awarded' => 6,
            'cancelled' => 6,
        ];

        $rfqs = collect();

        foreach ($statusDistribution as $status => $count) {
            $rfqs = $rfqs->merge(
                RFQ::factory()
                    ->count($count)
                    ->state(function () use ($status) {
                        $sentAt = $status === 'awaiting'
                            ? null
                            : fake()->dateTimeBetween('-45 days', 'now');

                        $deadlineAt = match ($status) {
                            'awaiting', 'open' => fake()->dateTimeBetween('+7 days', '+60 days'),
                            'closed', 'awarded' => fake()->dateTimeBetween('-45 days', '-1 day'),
                            default => fake()->dateTimeBetween('-15 days', '+30 days'),
                        };

                        return [
                            'status' => $status,
                            'sent_at' => $sentAt,
                            'deadline_at' => $deadlineAt,
                            'is_open_bidding' => $status === 'open' ? fake()->boolean(65) : false,
                        ];
                    })
                    ->create()
            );
        }

        $rfqs->each(function (RFQ $rfq) use ($suppliers): void {
            $quotesCount = match ($rfq->status) {
                'awaiting' => fake()->numberBetween(1, 3),
                'open' => fake()->numberBetween(2, 3),
                'closed', 'awarded' => fake()->numberBetween(1, 2),
                default => fake()->numberBetween(0, 1),
            };

            if ($quotesCount === 0) {
                return;
            }

            $selectedSuppliers = $quotesCount === 1
                ? collect([$suppliers->random()])
                : $suppliers->random(min($quotesCount, $suppliers->count()))->values();

            foreach ($selectedSuppliers as $supplier) {
                $start = $rfq->sent_at ? $rfq->sent_at->copy()->subHours(6) : now()->subDays(10);
                $end = $rfq->deadline_at ? $rfq->deadline_at->copy()->addDays(2) : now()->addDays(7);

                if ($end <= $start) {
                    $end = $start->copy()->addDays(2);
                }

                RFQQuote::factory()
                    ->for($rfq)
                    ->for($supplier)
                    ->state(function () use ($rfq, $start, $end) {
                        return [
                            'via' => $rfq->is_open_bidding ? 'bidding' : 'direct',
                            'submitted_at' => fake()->dateTimeBetween($start, $end),
                        ];
                    })
                    ->create();
            }
        });

        Order::factory()
            ->count(15)
            ->state(fn () => ['party_type' => 'supplier'])
            ->create();

        Order::factory()
            ->count(15)
            ->state(fn () => ['party_type' => 'customer'])
            ->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    private function prepareDemoFiles(): void
    {
        $disk = Storage::disk('local');

        foreach (['cad', 'attachments'] as $directory) {
            if (! $disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }
        }

        $placeholders = [
            'cad/demo-bracket.txt' => "Demo CAD placeholder file for RFQ previews.\n",
            'cad/housing-rev-a.txt' => "Housing Revision A CAD placeholder.\n",
            'cad/sample-bracket.step' => "STEP placeholder for bracket geometry.\n",
            'cad/assembly-v1.igs' => "IGES placeholder content.\n",
            'cad/demo-plate.stp' => "STP placeholder content.\n",
            'attachments/sample-quote.txt' => "Supplier quote notes placeholder.\n",
            'attachments/spec-sheet.txt' => "Spec sheet placeholder for demo attachments.\n",
            'attachments/quote-notes.txt' => "General quote notes placeholder file.\n",
            'attachments/spec-sheet.pdf' => "PDF placeholder for specifications.\n",
            'attachments/quote-summary.docx' => "DOCX placeholder for quote summary.\n",
        ];

        foreach ($placeholders as $path => $contents) {
            $this->putPlaceholder($disk, $path, $contents);
        }
    }

    private function putPlaceholder($disk, string $path, string $contents): void
    {
        if (! $disk->exists($path)) {
            $disk->put($path, $contents);
        }
    }
}
