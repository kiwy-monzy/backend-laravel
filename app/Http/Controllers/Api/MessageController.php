<?php

namespace App\Http\Controllers\Api;

use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MessageController extends ApiController
{
    public function create(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $required = $this->requireFields($data, ['website_id', 'name', 'email', 'message']);
        if ($required) {
            return $required;
        }

        $message = Message::create([
            'id' => (string) Str::uuid(),
            'website_id' => $data['website_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'subject' => $data['subject'] ?? '',
            'message' => $data['message'],
            'status' => 'pending',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return $this->json(['success' => true, 'message' => $message->toApi()]);
    }

    public function list(): JsonResponse
    {
        $messages = Message::orderByDesc('created_at')->get()
            ->map(fn (Message $m) => $m->toApi())
            ->values();

        return $this->json(['messages' => $messages]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $message = Message::find($data['id'] ?? '');
        if (! $message) {
            return $this->fail('Not found', 404);
        }

        foreach (['name', 'email', 'phone', 'subject', 'message', 'status'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $message->$field = $data[$field];
            }
        }
        if (array_key_exists('is_read', $data) && $data['is_read'] !== null) {
            $message->is_read = (bool) $data['is_read'];
        }
        $message->save();

        return $this->json(['success' => true, 'message' => $message->fresh()->toApi()]);
    }

    public function delete(Request $request): JsonResponse
    {
        $data = $this->body($request);
        Message::destroy($data['id'] ?? '');

        return $this->ok();
    }
}