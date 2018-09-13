<?php

Route::group(['middleware' => 'web', 'prefix' => 'apiv2', 'namespace' => 'Modules\APIv2\Http\Controllers'], function()
{
    Route::get('/', 'APIv2Controller@index');
});

Route::group(['middleware' => ['api'], 'namespace' => 'Modules\APIv2\Http\Controllers'], function () {
	Route::match(['get', 'post', 'delete', 'put'], '/api/v2', function () {
		return 'Project API v2';
	});
});
