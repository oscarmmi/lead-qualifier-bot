<?php

use App\Models\ChatTurn;
use App\Models\Lead;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use Database\Seeders\LeadDemoSeeder;
use Illuminate\Support\Str;

it('serves the public chat as an Inertia page in demo mode', function (): void {
    config()->set('services.gemini.api_key', null);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('llmMode', 'Demo mode')
            ->has('examples'));
});

it('qualifies a guest through a scripted BANT conversation and recommends a next step', function (): void {
    config()->set('services.gemini.api_key', null);

    expect(app(LlmClient::class))->toBeInstanceOf(FakeLlmClient::class);

    $response = $this->postJson('/chat', [
        'message' => 'I need a clinic chatbot that can qualify new patient enquiries.',
    ]);

    $response->assertOk()
        ->assertJsonPath('lead.status', 'chatting')
        ->assertJsonPath('reply', 'What budget range have you set aside for this? A rough range is fine.');

    $leadId = $response->json('lead.id');

    $this->postJson('/chat', [
        'lead_id' => $leadId,
        'message' => '$20,000 is approved for this project.',
    ])->assertOk()->assertJsonPath('lead.budget_band', '15k_plus');

    $this->postJson('/chat', [
        'lead_id' => $leadId,
        'message' => "I'm the founder and final decision maker.",
    ])->assertOk()->assertJsonPath('lead.authority', 'decision_maker');

    $complete = $this->postJson('/chat', [
        'lead_id' => $leadId,
        'message' => 'We want to launch within the next two months.',
    ]);

    $complete->assertOk()
        ->assertJsonPath('lead.status', 'qualified')
        ->assertJsonPath('lead.score', 98)
        ->assertJsonPath('lead.grade', 'A')
        ->assertJsonPath('lead.timeline', 'within_3_months');
    expect($complete->json('reply'))->toContain('Recommended next step: Schedule a discovery call this week.');

    $lead = Lead::query()->where('public_id', $leadId)->firstOrFail();
    expect($lead->score)->toBeBetween(0, 100)
        ->and($lead->turns()->count())->toBe(8)
        ->and($lead->turns()->where('role', 'user')->firstOrFail()->extracted_json)->toHaveKey('need_summary')
        ->and(ChatTurn::query()->where('lead_id', $lead->id)->where('role', 'assistant')->count())->toBe(4);
});

it('falls back to deterministic extraction when the configured LLM is unavailable', function (): void {
    $brokenClient = new class implements LlmClient
    {
        public function extractSlots(string $message, array $currentSlots): array
        {
            throw new RuntimeException('Provider timeout');
        }
    };

    app()->instance(LlmClient::class, $brokenClient);

    $this->postJson('/chat', ['message' => 'I need a clinic chatbot for appointment enquiries.'])
        ->assertOk()
        ->assertJsonPath('lead.need_summary', 'I need a clinic chatbot for appointment enquiries.')
        ->assertJsonPath('reply', 'What budget range have you set aside for this? A rough range is fine.');
});

it('restores a public conversation without exposing its database identifier', function (): void {
    $lead = Lead::query()->create(['public_id' => (string) Str::uuid()]);
    $lead->turns()->create(['role' => 'assistant', 'content' => 'What are you hoping to solve?']);

    $this->getJson("/chat/{$lead->public_id}")
        ->assertOk()
        ->assertJsonPath('lead.id', $lead->public_id)
        ->assertJsonPath('turns.0.content', 'What are you hoping to solve?')
        ->assertJsonMissingPath('lead.internal_id');
});

it('seeds the three fictional Northwind Labs example grades', function (): void {
    $this->seed(LeadDemoSeeder::class);

    $examples = Lead::query()->where('is_demo', true)->get();

    expect($examples)->toHaveCount(3)
        ->and($examples->pluck('grade')->sort()->values()->all())->toBe(['A', 'B', 'D'])
        ->and($examples->pluck('company')->unique()->all())->toBe(['Northwind Labs'])
        ->and($examples->every(fn (Lead $lead): bool => $lead->status === 'qualified'))->toBeTrue();
});
