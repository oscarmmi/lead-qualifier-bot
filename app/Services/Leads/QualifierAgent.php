<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use Throwable;

class QualifierAgent
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly FakeLlmClient $fallback,
        private readonly LeadScorer $scorer,
    ) {}

    public function respond(Lead $lead, string $message): string
    {
        if ($lead->status === 'qualified') {
            return 'This conversation is already complete. Your qualification summary is ready above.';
        }

        $currentSlots = [
            'budget_band' => $lead->budget_band,
            'authority' => $lead->authority,
            'need_summary' => $lead->need_summary,
            'timeline' => $lead->timeline,
        ];

        try {
            $extracted = $this->llm->extractSlots($message, $currentSlots);
        } catch (Throwable) {
            try {
                $extracted = $this->fallback->extractSlots($message, $currentSlots);
            } catch (Throwable) {
                $extracted = [];
            }
        }

        $normalized = $this->normalizeSlots($extracted);
        $lead->fill($normalized);
        $lead->turns()->create([
            'role' => 'user',
            'content' => $message,
            'extracted_json' => $normalized,
        ]);

        if ($this->allSlotsFilled($lead)) {
            $lead->score = $this->scorer->score($lead);
            $lead->grade = $this->scorer->grade($lead->score);
            $lead->status = 'qualified';
            $lead->save();

            $reply = "Thanks — that gives me a clear picture. {$this->scorer->summary($lead)} "
                ."Recommended next step: {$this->scorer->recommendation($lead->grade)}";
        } else {
            $lead->save();
            $reply = $this->nextQuestion($lead, $normalized);
        }

        $lead->turns()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);

        return $reply;
    }

    /** @param array<mixed> $slots
     * @return array<string, string>
     */
    private function normalizeSlots(array $slots): array
    {
        $allowed = [
            'budget_band' => ['under_1k', '1k_5k', '5k_15k', '15k_plus'],
            'authority' => ['decision_maker', 'co_decision_maker', 'influencer'],
            'timeline' => ['immediate', 'within_3_months', '3_6_months', '6_plus'],
        ];
        $normalized = [];

        foreach ($allowed as $field => $values) {
            $value = $slots[$field] ?? null;
            if (is_string($value) && in_array($value, $values, true)) {
                $normalized[$field] = $value;
            }
        }

        $need = $slots['need_summary'] ?? null;
        if (is_string($need) && trim($need) !== '') {
            $normalized['need_summary'] = mb_substr(trim($need), 0, 240);
        }

        return $normalized;
    }

    private function allSlotsFilled(Lead $lead): bool
    {
        return filled($lead->budget_band)
            && filled($lead->authority)
            && filled($lead->need_summary)
            && filled($lead->timeline);
    }

    /** @param array<string, string> $extracted */
    private function nextQuestion(Lead $lead, array $extracted): string
    {
        if (blank($lead->need_summary)) {
            return isset($extracted['budget_band']) || isset($extracted['authority']) || isset($extracted['timeline'])
                ? 'Got it. What are you hoping to improve or solve?'
                : 'What are you hoping to improve or solve? A sentence is plenty.';
        }

        if (blank($lead->budget_band)) {
            return 'What budget range have you set aside for this? A rough range is fine.';
        }

        if (blank($lead->authority)) {
            return 'Will you be making the decision, sharing it with others, or researching options?';
        }

        return 'When would you ideally like to get started: soon, within three months, in 3–6 months, or later?';
    }
}
