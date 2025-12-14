<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalBasicAuth
{
    /**
     * If PORTAL_BASIC_USER / PORTAL_BASIC_PASS are not set, middleware is a no-op.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow in-app JSON/XHR calls without prompting browser auth dialog.
        // This is meant to block casual "open /api/..." navigation, not to be a hard API security boundary.
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return $next($request);
        }

        $expectedUser = (string) config('portal.basic_user', '');
        $expectedPass = (string) config('portal.basic_pass', '');

        if ($expectedUser === '' || $expectedPass === '') {
            return $next($request);
        }

        $user = (string) $request->getUser();
        $pass = (string) $request->getPassword();

        if (hash_equals($expectedUser, $user) && hash_equals($expectedPass, $pass)) {
            return $next($request);
        }

        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="Borsa Portal", charset="UTF-8"',
        ]);
    }
}
