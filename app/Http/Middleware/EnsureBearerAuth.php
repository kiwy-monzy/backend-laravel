<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBearerAuth
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $claims = $this->jwt->decode(trim($m[1]));
        if (! $claims || empty($claims['sub'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = User::find($claims['sub']);
        if (! $user || ! $user->active) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_claims', $claims);

        return $next($request);
    }
}