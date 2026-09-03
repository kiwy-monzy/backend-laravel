<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="FGE Admin API",
 *     version="1.0.0",
 *     description="API for FGE Admin System"
 * )
 */
class AuthController extends ApiController
{
    public function __construct(private JwtService $jwt) {}

    /**
     * @OA\Post(
     *     path="/api/Login",
     *     summary="User login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username", "password"},
     *             @OA\Property(property="username", type="string", example="admin"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="id", type="string"),
     *             @OA\Property(property="username", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="role", type="string"),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials"
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        $user = User::where('username', $username)->first();
        if (! $user || ! $user->active || ! Hash::check($password, $user->password_hash)) {
            return $this->fail('Invalid username or password', 401);
        }

        $token = $this->jwt->issue($user->id, $user->username, $user->role);

        return $this->json([
            'success' => true,
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'token' => $token,
            'user' => $user->toPublicUser(),
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/Me",
     *     summary="Get current user info",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User info retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        return $this->json($this->user($request)->toPublicUser());
    }

    public function logout(): JsonResponse
    {
        return $this->ok();
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $data = $this->body($request);

        if (isset($data['new_password']) && $data['new_password'] !== '') {
            $current = $data['current_password'] ?? '';
            if (! Hash::check($current, $user->password_hash)) {
                return $this->fail('Current password is incorrect', 401);
            }
            $user->password_hash = Hash::make($data['new_password']);
        }

        if (isset($data['username']) && $data['username'] !== '') {
            $taken = User::where('username', $data['username'])
                ->where('id', '!=', $user->id)
                ->exists();
            if ($taken) {
                return $this->fail('username taken', 409);
            }
            $user->username = $data['username'];
        }

        if (isset($data['email']) && $data['email'] !== '') {
            $user->email = $data['email'];
        }

        $user->save();

        return $this->json($user->toPublicUser());
    }

    public function updateProfileImage(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $data = $this->body($request);
        $user->profile_image = $data['profile_image'] ?? null;
        $user->save();

        return $this->json($user->toPublicUser());
    }

    public function listUsers(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return $this->fail('Forbidden', 403);
        }
        $users = User::orderBy('created_at')->get()->map(fn (User $u) => $u->toPublicUser())->values();

        return $this->json(['users' => $users]);
    }

    public function updateUser(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return $this->fail('Forbidden', 403);
        }
        $data = $this->body($request);
        $user = User::find($data['id'] ?? '');
        if (! $user) {
            return $this->fail('Not found', 404);
        }

        if (isset($data['username']) && $data['username'] !== '') {
            $user->username = $data['username'];
        }
        if (isset($data['email']) && $data['email'] !== '') {
            $user->email = $data['email'];
        }
        if (isset($data['role']) && $data['role'] !== '') {
            $role = strtolower(trim($data['role']));
            $user->role = in_array($role, User::ROLES, true) ? $role : 'user';
        }
        if (array_key_exists('active', $data)) {
            $user->active = (bool) $data['active'];
        }
        if (isset($data['new_password']) && $data['new_password'] !== '') {
            $user->password_hash = Hash::make($data['new_password']);
        }
        $user->save();

        return $this->json($user->toPublicUser());
    }

    public function deleteUser(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return $this->fail('Forbidden', 403);
        }
        $data = $this->body($request);
        $user = User::find($data['id'] ?? '');
        if (! $user) {
            return $this->fail('Not found', 404);
        }
        if ($user->id === $this->user($request)->id) {
            return $this->fail('Cannot delete your own account', 400);
        }
        $user->delete();

        return $this->ok();
    }

    private function isAdmin(Request $request): bool
    {
        return $this->user($request)->hasAdminAccess();
    }
}