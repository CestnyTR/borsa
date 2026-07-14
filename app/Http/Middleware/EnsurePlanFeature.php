<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan özelliği kapısı. Kullanım: ->middleware('plan:branded_board')
 * İlgili özellik kullanıcının planında açık değilse fiyatlandırmaya yönlendirir.
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (!$user || !$user->planFeature($feature, false)) {
            if ($request->expectsJson()) {
                abort(402, 'Bu özellik için plan yükseltmesi gerekli.');
            }

            return redirect()
                ->route('billing.pricing')
                ->with('error', 'Bu özellik Pro plana özeldir. Yükseltmek için planları inceleyin.');
        }

        return $next($request);
    }
}
