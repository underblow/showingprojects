<?php

namespace App\Http\Middleware;

use App\Services\TextHelper;
use Closure;
use Auth;

class Can
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $permission, $action)
    {
        $user = Auth::user();

        if($user->can($permission, $action)) {
            return $next($request);
        } else {
            return response()->error(TextHelper::t("You have no permission to perform this action"), 403);
        }
    }
}
