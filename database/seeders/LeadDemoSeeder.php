<?php

namespace Database\Seeders;

use App\Models\Lead;
use Illuminate\Database\Seeder;

class LeadDemoSeeder extends Seeder
{
    public function run(): void
    {
        $examples = [
            [
                'public_id' => 'a1000000-0000-4000-8000-000000000001',
                'name' => 'Avery Chen',
                'email' => 'avery@northwind.example',
                'budget_band' => '15k_plus',
                'authority' => 'decision_maker',
                'need_summary' => 'Qualify clinic enquiries and reduce manual follow-up.',
                'timeline' => 'immediate',
                'score' => 100,
                'grade' => 'A',
            ],
            [
                'public_id' => 'b2000000-0000-4000-8000-000000000002',
                'name' => 'Morgan Ellis',
                'email' => 'morgan@northwind.example',
                'budget_band' => '5k_15k',
                'authority' => 'influencer',
                'need_summary' => 'Explore a chatbot for appointment enquiries.',
                'timeline' => '3_6_months',
                'score' => 74,
                'grade' => 'B',
            ],
            [
                'public_id' => 'd4000000-0000-4000-8000-000000000004',
                'name' => 'Taylor Quinn',
                'email' => 'taylor@northwind.example',
                'budget_band' => 'under_1k',
                'authority' => 'influencer',
                'need_summary' => 'Browsing options; no active project yet.',
                'timeline' => '6_plus',
                'score' => 28,
                'grade' => 'D',
            ],
        ];

        foreach ($examples as $example) {
            Lead::query()->updateOrCreate(
                ['public_id' => $example['public_id']],
                [...$example, 'company' => 'Northwind Labs', 'status' => 'qualified', 'is_demo' => true],
            );
        }
    }
}
