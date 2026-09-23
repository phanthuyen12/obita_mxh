<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\AiBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * CRUD for workspace-level Dify bots. Admins can create several bots; the
 * flagged default bot backs the AI auto-reply when a channel has no
 * channel-specific API key. Keys are never returned to the client.
 */
class AiBotController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function botData(AiBot $bot): array
    {
        return [
            'id' => $bot->id,
            'name' => $bot->name,
            'dify_base_url' => $bot->dify_base_url,
            'key_set' => $bot->dify_api_key !== null && $bot->dify_api_key !== '',
            'is_active' => $bot->is_active,
            'is_default' => $bot->is_default,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        $bots = AiBot::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $bots->map(fn (AiBot $bot): array => $this->botData($bot))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeBotManagement($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'dify_api_key' => ['required', 'string', 'max:500'],
            'dify_base_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $bot = DB::transaction(function () use ($validated, $request): AiBot {
            if ($validated['is_default'] ?? false) {
                AiBot::query()
                    ->where('workspace_id', $request->user()->currentWorkspace->id)
                    ->update(['is_default' => false]);
            }

            return AiBot::query()->create([
                'workspace_id' => $request->user()->currentWorkspace->id,
                ...$validated,
            ]);
        });

        return response()->json(['bot' => $this->botData($bot)], SymfonyResponse::HTTP_CREATED);
    }

    public function update(Request $request, AiBot $bot): JsonResponse
    {
        $this->authorizeBotManagement($request);
        abort_unless($bot->workspace_id === $request->user()->currentWorkspace->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'dify_api_key' => ['nullable', 'string', 'max:500'],
            'dify_base_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $bot): void {
            if (($validated['is_default'] ?? null) === true) {
                AiBot::query()
                    ->where('workspace_id', $bot->workspace_id)
                    ->whereKeyNot($bot->id)
                    ->update(['is_default' => false]);
            }

            // An empty key keeps the existing one — the client never sees it back.
            if (($validated['dify_api_key'] ?? '') === '') {
                unset($validated['dify_api_key']);
            }

            $bot->update($validated);
        });

        return response()->json(['bot' => $this->botData($bot->fresh())]);
    }

    public function destroy(Request $request, AiBot $bot): JsonResponse
    {
        $this->authorizeBotManagement($request);
        abort_unless($bot->workspace_id === $request->user()->currentWorkspace->id, 404);

        $bot->delete();

        return response()->json(['deleted' => true]);
    }

    private function authorizeBotManagement(Request $request): void
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $workspace && ($request->user()->isAccountOwner() || $request->user()->can('manageTeam', $workspace)),
            SymfonyResponse::HTTP_FORBIDDEN,
        );
    }
}
