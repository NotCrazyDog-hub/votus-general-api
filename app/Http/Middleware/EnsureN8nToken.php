<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureN8nToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.n8n.import_token');

        abort_unless(
            $expected && hash_equals(
                $expected,
                (string) $request->header('X-N8N-Token')
            ),
            401
        );

        return $next($request);
    }
}