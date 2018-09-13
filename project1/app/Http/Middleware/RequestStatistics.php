<?php

namespace App\Http\Middleware;

use Closure;
use App\Exceptions\ProjectValidationHttpException;
use Illuminate\Support\Facades\Route;
use App\RequestStatistic;
use Jenssegers\Agent\Agent;

class RequestStatistics
{
	/**
	 * Handle an incoming request.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \Closure $next
	 * @param string|null $guard
	 *
	 * @return mixed
	 */
	public function handle($request, Closure $next, $guard = null)
	{
        $agent = new Agent();

        $platform = $agent->platform();
        $platform_version = $agent->version($platform);

        $platform = $platform ? $platform : "";
        $platform_version = $platform_version ? $platform_version : "";

        $rs = RequestStatistic::where('route',$request->route()->uri)
            ->where('method',$request->getMethod())
            ->where('os',$platform)
            ->where('os_version',$platform_version)
            ->limit(1)
            ->first();

        if($rs){
            $rs->visitor_counter++;
            $rs->save();
        }else{
            preg_match("/project\/([0-9a-zA-Z\.]+)\s/i", $request->header('User-Agent'), $output_array);

            $rs = new RequestStatistic();

            $rs->app_version = isset($output_array[1]) ? $output_array[1] : "";
            $rs->visitor_counter++;
            $rs->os = $platform;
            $rs->os_version = $platform_version;
            $rs->route = $request->route()->uri;
            $rs->method = $request->getMethod();
            $rs->visitor_counter = 1;

            $rs->save();
        }

        $response = $next($request);

        return $response;
	}
}
