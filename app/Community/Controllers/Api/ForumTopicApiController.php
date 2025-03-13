<?php

declare(strict_types=1);

namespace App\Community\Controllers\Api;

use App\Community\Requests\GateForumTopicRequest;
use App\Community\Requests\StoreForumTopicRequest;
use App\Community\Requests\UpdateForumTopicRequest;
use App\Http\Controller;
use App\Models\Forum;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use Illuminate\Http\JsonResponse;

class ForumTopicApiController extends Controller
{
    public function store(
        ForumCategory $category,
        Forum $forum,
        StoreForumTopicRequest $request
    ): JsonResponse {
        $this->authorize('create', [ForumTopic::class, $forum]);

        $newForumTopicComment = submitNewTopic(
            $request->user(),
            $forum->id,
            $request->input('title'),
            $request->input('body'),
        );

        return response()->json([
            'success' => true,
            'newTopicId' => $newForumTopicComment->forumTopic->id,
        ]);
    }

    public function update(
        ForumTopic $topic,
        UpdateForumTopicRequest $request
    ): JsonResponse {
        $this->authorize('update', $topic);

        $topic->title = $request->input('title');
        $topic->save();

        return response()->json(['success' => true]);
    }

    public function destroy(ForumTopic $topic): JsonResponse
    {
        $this->authorize('delete', $topic);

        $topic->delete();

        return response()->json(['success' => true]);
    }

    public function gate(
        ForumTopic $topic,
        GateForumTopicRequest $request
    ): JsonResponse {
        $this->authorize('gate', $topic);

        $topic->required_permissions = $request->input('permissions');
        $topic->save();

        return response()->json(['success' => true]);
    }
}
