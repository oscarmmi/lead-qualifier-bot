<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Leads\QualifierAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function store(Request $request, QualifierAgent $agent): JsonResponse
    {
        $validated = $request->validate([
            'lead_id' => ['nullable', 'uuid'],
            'message' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $lead = isset($validated['lead_id'])
            ? Lead::query()->where('public_id', $validated['lead_id'])->firstOrFail()
            : Lead::query()->create(['public_id' => (string) Str::uuid()]);

        $reply = $agent->respond($lead, $validated['message']);
        $lead->refresh();

        return response()->json([
            'reply' => $reply,
            'lead' => $this->leadPayload($lead),
        ]);
    }

    public function show(string $publicId): JsonResponse
    {
        $lead = Lead::query()->where('public_id', $publicId)->firstOrFail();

        return response()->json([
            'lead' => $this->leadPayload($lead),
            'turns' => $lead->turns()
                ->orderBy('id')
                ->get(['role', 'content'])
                ->map(fn ($turn): array => ['role' => $turn->role, 'content' => $turn->content]),
        ]);
    }

    /** @return array<string, mixed> */
    private function leadPayload(Lead $lead): array
    {
        return [
            'id' => $lead->public_id,
            'status' => $lead->status,
            'score' => $lead->score,
            'grade' => $lead->grade,
            'budget_band' => $lead->budget_band,
            'authority' => $lead->authority,
            'need_summary' => $lead->need_summary,
            'timeline' => $lead->timeline,
        ];
    }
}
