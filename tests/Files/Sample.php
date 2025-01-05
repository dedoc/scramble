<?php

namespace Dedoc\Scramble\Tests\Files;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class Sample
{
    public function handleUserRequest(Request $request, int $userId): \Illuminate\Http\JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'age' => 'nullable|integer|min:18',
            'preferences' => 'nullable|array',
            'preferences.notifications' => 'boolean',
            'preferences.theme' => 'string|in:light,dark',
        ]);

        $user = SampleUserModel::find($userId);

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $oldData = $user->only(['name', 'email', 'age', 'preferences']);
        $user->update($validatedData);

        if ($request->has('send_notification') && $request->boolean('send_notification')) {
            // Simulate sending notification
            Log::info("Sending notification to user $userId.");
            // Notification::send($user, new UserUpdatedNotification($validatedData));
        }

        $stats = $this->calculateUserStats($user);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user,
            'stats' => $stats,
        ], 200);
    }

    public function calculateUserStats(SampleUserModel $user): array
    {
        $postsCount = $user->posts()->count();
        $commentsCount = $user->comments()->count();
        $lastActive = $user->last_active_at ?? 'Never';

        return [
            'posts' => $postsCount,
            'comments' => $commentsCount,
            'last_active' => $lastActive,
        ];
    }

    public function handleBatchUserUpdates(Request $request): \Illuminate\Http\JsonResponse
    {
        $userUpdates = $request->input('users', []);
        $results = [];

        foreach ($userUpdates as $update) {
            $validator = Validator::make($update, [
                'user_id' => 'required|integer|exists:users,id',
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:users,email',
                'age' => 'nullable|integer|min:18',
            ]);

            if ($validator->fails()) {
                $results[] = [
                    'user_id' => $update['user_id'] ?? null,
                    'status' => 'failed',
                    'errors' => $validator->errors()->all(),
                ];

                continue;
            }

            $data = $validator->validated();
            $user = SampleUserModel::find($data['user_id']);

            if (! $user) {
                $results[] = [
                    'user_id' => $data['user_id'],
                    'status' => 'failed',
                    'errors' => ['User not found.'],
                ];

                continue;
            }

            $user->update($data);
            $results[] = [
                'user_id' => $data['user_id'],
                'status' => 'updated',
            ];
        }

        return response()->json($results);
    }
}
