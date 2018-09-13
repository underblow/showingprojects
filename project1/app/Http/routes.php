<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::group(['middleware' => ['web', 'api']], function () {
	Route::match(['get', 'post', 'delete', 'put'], '/', function () {
		return 'project';
	});
});

$api->version('v1', ['prefix' => 'v1'], function ($api) {
	$api->group(['middleware' => ['api']], function ($api) {
		$api->post('auth/login', 'Auth\AuthController@postLogin');
		$api->post('auth/password/reset', 'Auth\AuthController@reset');
		$api->get('invites/{code}', 'InviteController@getInvite');
	});

	$api->group(['middleware' => ['api','invites']], function ($api) {
		$api->get('/public/tasks/{id}', 'TaskController@getTask');
		$api->get('/public/tasks/{id}/comments', 'TaskController@getComments');
	});

	$api->group(['middleware' => ['api', 'jwt-auth', 'overduetasks']], function ($api) {
		//users
		$api->get('users', 'UserController@index');
		$api->get('users/me', 'UserController@getMe');

		//patients
		$api->get('patients', 'PatientController@index')->middleware('can:my.patients,view');
		$api->get('patients/count', 'PatientController@getCount')->middleware('can:my.patients,view');
		$api->get('patients/{id}', 'PatientController@getPatient')->middleware('can:my.patients,view');
	});

	$api->get('reports/render/benchmark/{id}', 'ReportController@renderBenchmark');
});

$api->version('v2', ['prefix' => 'v2'], function ($api) {
	$api->group(['middleware' => ['api', 'jwt-auth', 'overduetasks']], function ($api) {
		//patients
		$api->post('patients', '\Modules\APIv2\Http\Controllers\PatientController@postPatient')->middleware('can:my.patients,create');
		$api->patch('patients/{id}', '\Modules\APIv2\Http\Controllers\PatientController@patchPatients')->middleware('can:my.patients,edit');
	});
});