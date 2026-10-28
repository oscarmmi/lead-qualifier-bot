<?php

namespace App\Services\Leads;

use App\Models\Lead;

class LeadScorer
{
    /**
     * BANT weights: need 30, budget 25, authority 25, and timeline 20 points.
     * Unknown values earn zero. A concrete need earns 30; casual browsing earns 5.
     */
    /** @return int<0, 100> */
    public function score(Lead $lead): int
    {
        $budgetPoints = [
            '15k_plus' => 25,
            '5k_15k' => 20,
            '1k_5k' => 12,
            'under_1k' => 5,
        ][$lead->budget_band] ?? 0;

        $authorityPoints = [
            'decision_maker' => 25,
            'co_decision_maker' => 20,
            'influencer' => 12,
        ][$lead->authority] ?? 0;

        $needPoints = match (true) {
            blank($lead->need_summary) => 0,
            preg_match('/\b(browsing|no active project|no project yet|not ready)\b/i', (string) $lead->need_summary) === 1 => 5,
            default => 30,
        };

        $timelinePoints = [
            'immediate' => 20,
            'within_3_months' => 18,
            '3_6_months' => 12,
            '6_plus' => 6,
        ][$lead->timeline] ?? 0;

        $score = $budgetPoints + $authorityPoints + $needPoints + $timelinePoints;

        if ($score < 0) {
            return 0;
        }

        return $score > 100 ? 100 : $score;
    }

    public function grade(int $score): string
    {
        return match (true) {
            $score >= 80 => 'A',
            $score >= 60 => 'B',
            $score >= 40 => 'C',
            default => 'D',
        };
    }

    public function summary(Lead $lead): string
    {
        $authority = match ($lead->authority) {
            'decision_maker' => 'a decision-maker',
            'co_decision_maker' => 'a co-decision-maker',
            'influencer' => 'an evaluator',
            default => 'a visitor',
        };

        $needText = preg_replace(
            '/^(?:i|we|our team)\s+(?:need|want|are looking for|hope to|would like)\s+(?:a|an|the)?\s*/i',
            '',
            (string) $lead->need_summary,
        ) ?? (string) $lead->need_summary;
        $need = $lead->need_summary === 'Browsing options; no active project yet.'
            ? 'is browsing without an active project'
            : 'needs '.rtrim($needText, '.');

        $timeline = match ($lead->timeline) {
            'immediate' => 'an immediate timeline',
            'within_3_months' => 'a timeline within three months',
            '3_6_months' => 'a 3–6 month timeline',
            '6_plus' => 'a flexible timeline beyond six months',
            default => 'an unspecified timeline',
        };

        $budget = match ($lead->budget_band) {
            '15k_plus' => 'a $15k+ budget',
            '5k_15k' => 'a $5k–$15k budget',
            '1k_5k' => 'a $1k–$5k budget',
            'under_1k' => 'a budget under $1k',
            default => 'an unconfirmed budget',
        };

        return ucfirst("{$authority} {$need}, with {$budget} and {$timeline}.");
    }

    public function recommendation(string $grade): string
    {
        return match ($grade) {
            'A' => 'Schedule a discovery call this week.',
            'B' => 'Offer a tailored product walkthrough.',
            'C' => 'Send a concise overview and check back soon.',
            default => 'Share a self-guided resource; no sales follow-up needed yet.',
        };
    }
}
