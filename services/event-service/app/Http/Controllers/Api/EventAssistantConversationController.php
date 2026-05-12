<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventAssistantChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventAssistantConversationController extends Controller
{
    public function __construct(
        private readonly EventAssistantChatService $chatService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        return response()->json([
            'items' => $this->chatService->conversations((int) $user['id']),
        ]);
    }

    public function show(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $conversation = $this->chatService->conversation((int) $user['id'], $conversationId);

        if ($conversation === null) {
            abort(404);
        }

        return response()->json($conversation);
    }
}
