<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiLlmClient implements LlmClient
{
    /** @return array<string, string> */
    public function extractSlots(string $message, array $currentSlots): array
    {
        $model = config('services.gemini.model');
        $apiKey = config('services.gemini.api_key');

        $response = Http::timeout(12)
            ->connectTimeout(5)
            ->acceptJson()
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'systemInstruction' => [
                    'parts' => [[
                        'text' => 'Extract only facts explicitly stated in the latest visitor message. Return one JSON object with optional string keys budget_band, authority, need_summary, timeline. Allowed budget_band values: under_1k, 1k_5k, 5k_15k, 15k_plus. Allowed authority values: decision_maker, co_decision_maker, influencer. Allowed timeline values: immediate, within_3_months, 3_6_months, 6_plus. Keep need_summary concise and in English. Map casual browsing to "Browsing options; no active project yet." Do not guess. Return JSON only.',
                    ]],
                ],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => json_encode([
                            'known_slots' => $currentSlots,
                            'latest_message' => $message,
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.1,
                ],
            ]);

        $response->throw();
        $content = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Gemini returned an empty extraction response.');
        }

        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)) ?? trim($content);
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return array_filter($decoded, fn ($value): bool => is_string($value) && $value !== '');
    }
}
