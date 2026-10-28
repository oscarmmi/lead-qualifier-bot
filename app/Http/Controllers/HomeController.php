<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Leads\LeadScorer;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(LeadScorer $scorer): Response
    {
        $examples = Lead::query()
            ->where('is_demo', true)
            ->where('status', 'qualified')
            ->orderByRaw("CASE grade WHEN 'A' THEN 1 WHEN 'B' THEN 2 ELSE 3 END")
            ->get()
            ->map(fn (Lead $lead): array => [
                'name' => $lead->name,
                'score' => $lead->score,
                'grade' => $lead->grade,
                'need_summary' => $lead->need_summary,
                'next_step' => $scorer->recommendation((string) $lead->grade),
            ])
            ->all();

        return Inertia::render('Welcome', [
            'examples' => $examples,
            'llmMode' => filled(config('services.gemini.api_key')) ? 'Gemini connected' : 'Demo mode',
        ]);
    }
}
