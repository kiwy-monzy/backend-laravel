<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

abstract class ApiController extends Controller
{
    protected function json(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function ok(array $data = []): JsonResponse
    {
        return $this->json(array_merge(['success' => true], $data));
    }

    protected function fail(string $message, int $status = 400): JsonResponse
    {
        return $this->json(['success' => false, 'message' => $message], $status);
    }

    protected function user(Request $request): \App\Models\User
    {
        return $request->attributes->get('auth_user');
    }

    /**
     * The organization to file uploads and records under.
     *
     * Nullable rather than required: the public endpoints — a visitor making a
     * donation, submitting the contact form — carry no authenticated user, and
     * MediaLibrary handles a null by writing to `_shared` instead of failing.
     */
    protected function organizationId(Request $request): ?string
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();

        return $user?->organization_id
            ?? \App\Models\Organization::orderBy('created_at')->value('id');
    }

    protected function body(Request $request): array
    {
        return $request->json()->all() ?: $request->all();
    }

    protected function requireFields(array $data, array $fields): ?JsonResponse
    {
        $validator = Validator::make($data, array_fill_keys($fields, 'required'));
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 400);
        }
        return null;
    }
}