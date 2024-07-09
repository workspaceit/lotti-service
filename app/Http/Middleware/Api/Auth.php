<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth as AuthFacade;
use Illuminate\Support\Facades\Log;

class Auth
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
        if (! AuthFacade::guard('api')->check()) {
            Log::info("You are not authenticated". json_encode($request->all()));

            return response()->json([
                'status' => 'error',
                'message' => 'You are not authenticated.',
                'data' => []
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
