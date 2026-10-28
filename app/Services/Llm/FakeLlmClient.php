<?php

namespace App\Services\Llm;

use Illuminate\Support\Str;

class FakeLlmClient implements LlmClient
{
    /** @return array<string, string> */
    public function extractSlots(string $message, array $currentSlots): array
    {
        $text = Str::lower($message);
        $extracted = [];

        if (preg_match('/\b(?:under|below|less than|up to)\s+\$?(?:1k|1,000|1000)\b/i', $text) || preg_match('/\bno budget\b/i', $text) || preg_match('/\$?500\b/i', $text)) {
            $extracted['budget_band'] = 'under_1k';
        } elseif (preg_match('/\b(?:under|below|less than|up to)\s+\$?(?:5k|5,000|5000)\b/i', $text) || preg_match('/(?:1k|1,000|1000)\s*(?:-|–|to|and)\s*(?:5k|5,000|5000)/i', $text)) {
            $extracted['budget_band'] = '1k_5k';
        } elseif (preg_match('/\b(?:under|below|less than|up to)\s+\$?(?:15k|15,000|15000)\b/i', $text) || preg_match('/(?:5k|5,000|5000)\s*(?:-|–|to|and)\s*(?:15k|15,000|15000)/i', $text)) {
            $extracted['budget_band'] = '5k_15k';
        } elseif (preg_match('/(?:\$\s*)?(?:15k|15,000|15000|20k|20,000|20000|25k|25,000|25000|30k|30,000|30000|over\s+\$?15|more than\s+\$?15|above\s+\$?15)/i', $text)) {
            $extracted['budget_band'] = '15k_plus';
        } elseif (preg_match('/(?:\$\s*)?(?:5k|5,000|5000|10k|10,000|10000|12k|12,000|12000|between\s+\$?5|5\s*[-–to]+\s*15)/i', $text)) {
            $extracted['budget_band'] = '5k_15k';
        } elseif (preg_match('/(?:\$\s*)?(?:1k|1,000|1000|2k|2,000|2000|3k|3,000|3000|4k|4,000|4000|under\s+\$?5|less than\s+\$?5)/i', $text)) {
            $extracted['budget_band'] = '1k_5k';
        } elseif (preg_match('/(?:under\s+\$?1|less than\s+\$?1|below\s+\$?1|no budget|\$?500\b)/i', $text)) {
            $extracted['budget_band'] = 'under_1k';
        }

        if (preg_match('/\b(co-decision|co decision|jointly decide|share the decision|we decide together)\b/i', $text)) {
            $extracted['authority'] = 'co_decision_maker';
        } elseif (preg_match('/\b(not the decision maker|not a decision maker|do not make the decision|don.t make the final decision)\b/i', $text)) {
            $extracted['authority'] = 'influencer';
        } elseif (preg_match('/\b(final decision|decision maker|decision-maker|i decide|i make the call|founder|owner|chief executive|\bceo\b)\b/i', $text)) {
            $extracted['authority'] = 'decision_maker';
        } elseif (preg_match('/\b(recommend|influence|evaluating|shortlist|researching|gathering options)\b/i', $text)) {
            $extracted['authority'] = 'influencer';
        }

        if (preg_match('/\b(just browsing|only browsing|just looking|no active project|not ready|just exploring|browsing options)\b/i', $text)) {
            $extracted['need_summary'] = 'Browsing options; no active project yet.';
        } elseif (empty($currentSlots['need_summary']) && preg_match('/\b(need|looking for|want to|hoping to|trying to|exploring|build|improve|automate|qualify|chatbot|chat bot|patient enquiry|lead)\b/i', $text)) {
            $extracted['need_summary'] = Str::limit(trim($message), 240, '');
        }

        if (preg_match('/\b(today|immediately|asap|this week|right away|already approved|next month|within (?:the )?next month|in (?:the )?next month|within 1 month|within one month)\b/i', $text)) {
            $extracted['timeline'] = 'immediate';
        } elseif (preg_match('/\b(within (?:the )?(?:next )?3 months|within (?:the )?next two months|in (?:the )?next two months|next quarter|within 90 days|in two months|in 2 months|in one to three months)\b/i', $text)) {
            $extracted['timeline'] = 'within_3_months';
        } elseif (preg_match('/\b(3 to 6 months|3-6 months|3–6 months|within six months|in six months|this year)\b/i', $text)) {
            $extracted['timeline'] = '3_6_months';
        } elseif (preg_match('/\b(no timeline|not sure when|more than six months|6\+ months|next year|someday|later)\b/i', $text)) {
            $extracted['timeline'] = '6_plus';
        }

        return $extracted;
    }
}
