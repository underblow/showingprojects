<?php

namespace App\Http\Middleware;

use App\Services\TextHelper;
use Closure;
use JWTAuth;
use Exception;
use Illuminate\Support\Facades\DB;
use Auth;
use Carbon\Carbon;

class AuthJWT
{
    public function handle($request, Closure $next)
    {
        try {
	        $token = JWTAuth::getToken();

	        $store_token = DB::table('tokens')->where('token_id', $token)->first();

	        if($store_token->logout_reason === 1){
              return response()->error(TextHelper::t('Your account was used in other device.'), 401);
	        }

	        if($store_token->logout_reason === 2){
              return response()->error(TextHelper::t('Your user name was changed. Please, enter valid credentials'), 401);
	        }

	        if($store_token->logout_reason === 3){
              return response()->error(TextHelper::t('The user was deactivated. You no longer have access to the system. Please, consult your administration.'), 401);
	        }

	        $parsedToken = JWTAuth::parseToken();
	        $parsedToken->authenticate($store_token ? $store_token->refresh_token : $token);

	        $refresh_token = JWTAuth::setToken($store_token->refresh_token)->refresh();
	        $payload = JWTAuth::getPayload();

	        $user = Auth::user();
	        //if the user has smaller TTL than the default value, honour that
	        if ($user && $user->token_ttl_min) {
	            $expiresAt = $payload['exp'];
	            $expireDate = Carbon::createFromTimestamp($expiresAt)
	                            ->subMinutes(config('jwt.ttl'))
	                            ->addMinutes($user->token_ttl_min);

	            if ($expireDate->lt(Carbon::now())) {
	                throw new \Tymon\JWTAuth\Exceptions\TokenExpiredException;
	            }
	        }

	        DB::table('tokens')
		        ->where('token_id', $token)
		        ->update(['refresh_token' => $refresh_token]);
        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return response()->error(TextHelper::t('Session is Invalid'),403);
            }else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return response()->error(TextHelper::t('Your session has expired. Please, log into the App again'),401);
            }else{
                return response()->error(TextHelper::t('Forbidden'),403);
            }
        }

        return $next($request);
    }
}
