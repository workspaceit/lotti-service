<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth as AuthFacade;

class Guest
{
    /**
     * Handle an incoming request.
     *
     * @param  $request
     * @param  Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (AuthFacade::guard('api')->check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Logged in user can\'t do that action.',
                'data' => []
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
