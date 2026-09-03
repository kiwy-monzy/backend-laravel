<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The gate on every module route: `->middleware('module:crm')`.
 *
 * **Enforced here rather than in each controller.** A module is dozens of
 * routes written by whoever added it; relying on each one to remember the
 * check is how a single unguarded endpoint ends up exposing another
 * organization's invoices. The nav hides what a member cannot reach, but this
 * is what actually stops them.
 *
 * Write verbs additionally require an active subscription — a lapsed plan
 * leaves the data readable, never editable.
 */
class EnsureModuleAccess
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next, string $module)
    {
        if (! Modules::exists($module)) {
            throw new NotFoundHttpException("No such module: $module");
        }

        $user = $request->user();
        $organization = $user?->organization;

        if (! $organization) {
            throw new AccessDeniedHttpException('You do not belong to an organization yet.');
        }

        if (! $user->allowedModule($module)) {
            throw new AccessDeniedHttpException(
                'Your role does not have access to ' . Modules::label($module) . '.'
            );
        }

        // Sections can be withheld individually, so entering the module is not
        // the end of it: hiding a tab in the rail while its URL still answered
        // would be decoration, not a permission.
        if ($section = $this->sectionOf($request, $module)) {
            if (! $user->allowedSection($module, $section)) {
                throw new AccessDeniedHttpException(
                    'Your role does not have access to that part of ' . Modules::label($module) . '.'
                );
            }
        }

        if (in_array($request->method(), self::WRITE_METHODS, true) && ! $organization->isActive()) {
            throw new AccessDeniedHttpException(
                'Your subscription has lapsed, so this organization is read-only. '
                . 'Renew it from Subscription to make changes again.'
            );
        }

        return $next($request);
    }

    /**
     * Which declared section this request belongs to, if any.
     *
     * Matched on the section's `active` pattern so every route under a tab —
     * its create, edit and delete, not just the list — is covered by the one
     * grant. A request matching no declared section is unrestricted, which is
     * what keeps a module's odd extra route from becoming unreachable.
     */
    private function sectionOf(Request $request, string $module): ?string
    {
        $manifest = Modules::find($module);

        foreach ($manifest['sections'] ?? [] as $section) {
            $pattern = $section['active'] ?? ($section['route'] ?? null);

            if ($pattern && $request->routeIs($pattern)) {
                return \App\Support\ModuleNav::key($section);
            }
        }

        return null;
    }
}
