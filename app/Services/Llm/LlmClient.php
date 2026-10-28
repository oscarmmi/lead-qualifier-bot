<?php

namespace App\Services\Llm;

interface LlmClient
{
    /** @param array<string, string|null> $currentSlots
     * @return array<string, string>
     */
    public function extractSlots(string $message, array $currentSlots): array;
}
