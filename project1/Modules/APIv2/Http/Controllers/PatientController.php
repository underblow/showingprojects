<?php

namespace Modules\APIv2\Http\Controllers;

use App\Patient;
use App\Affiliate;
use App\Services\TextHelper;
use Auth;
use DB;
use Schema;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * @Resource("Patients v2", uri="/v2/patients")
 *
 * @resource Patients
 */
class PatientController extends APIv2Controller
{
	/**
	 * Create new patient
	 *
	 * Create new patient
	 *
	 * @Post("/")
	 * @Request({"first_name":"First Name","last_name":"Last name","birthdate": 123456789,"birth_country": "German","birth_city": "Berlin","email": "test@test.com","phone": "+1-000-00-000-000","count_cases": 2,"user_id":11})
	 * @Response(201,body={"is_active": 1,"first_name": "Mark","last_name": "Man","id_code": "RM5N28","user_id": 1,"updated_at": "2017-04-12 12:17:58","created_at": "2017-04-12 12:17:58","id": 1})
	 *
	 * @return JSON
	 */
	public function postPatient(Request $request)
	{
		$user = Auth::user();

		$this->validate($request, [
			'first_name' => 'required',
			'last_name' => 'required',
			'email' => 'required|email',
			'birthdate' => 'required',
			'user_id' => 'integer'
		]);

		$affiliate = Affiliate::findOrFail($user->affiliate_id);

		//prepare id dode
		$digits = 3;

		do {
			$X = rand(0,9);
			$YYY = implode("",[chr(rand(65, 90)),chr(rand(65, 90)),chr(rand(65, 90))]);
			$id_code = substr($affiliate->abbreviation, 0, 2) . substr_replace($YYY, $X, 1, 0);
		}while(Patient::where('id_code','=',$id_code)->first());

		$birthdate = Carbon::createFromTimestamp($request->birthdate);

		$user_id = $request->has('user_id') ? $request->get('user_id') : $user->id;

		$patient = new Patient();
		$patient['first_name'] = $request->first_name;
		$patient['last_name'] = $request->last_name;
		$patient['id_code'] = $id_code;
		$patient['user_id'] = $user_id;
		$patient['email'] = $request->email;
		$patient['birthdate'] = $birthdate->toDateString();

		if($request->has('phone') && $request->exists('phone'))
			$patient['phone'] = $request->phone;

		if($request->has('birth_city') && $request->exists('birth_city'))
			$patient['birth_city'] = $request->birth_city;

		if($request->has('birth_country') && $request->exists('birth_country'))
			$patient['birth_country'] = $request->birth_country;

		$patient->save();

		return response()->success($patient,null,201);
	}

    /**
     * Update patient
     *
     * Update patient.
     *
     * @Patch("/:id")
     * @Request({"first_name": "Man","last_name": "Manovich","birthdate": 123456789,"birth_country": "German","birth_city": "Berlin","email": "test@test.com","phone": "+1-000-00-000-000","count_cases": 2})
     * @Response(200,body={"id": 1,"id_code": "RMB3IU","first_name": "Martinovich","last_name": "Sam","user_id": 7,"is_active": 1})
     *
     * @return JSON patient
     */
	public function patchPatients($id, Request $request) {
      $user = Auth::user();

      $this->validate($request, [
          'first_name' => 'required',
          'last_name' => 'required',
        'email' => 'required|email',
        'birthdate' => 'required',
      ]);

      $patient = Patient::find($id);

      if (!$patient) {
          return response()->error(TextHelper::t('Patient not found'), 404);
      }

      if($patient->user_id != $user->id){
          return response()->error(TextHelper::t('You not owner a patient'), 403);
      }

      $birthdate = Carbon::createFromTimestamp($request->birthdate);

      $patient->first_name = $request['first_name'];
      $patient->last_name = $request['last_name'];
      $patient['email'] = $request->email;
      $patient['birthdate'] = $birthdate->toDateString();

      if($request->has('phone') && $request->exists('phone'))
        $patient['phone'] = $request->phone;

      if($request->has('birth_city') && $request->exists('birth_city'))
        $patient['birth_city'] = $request->birth_city;

      if($request->has('birth_country') && $request->exists('birth_country'))
        $patient['birth_country'] = $request->birth_country;

      $patient->save();

      return response()->success($patient->makeHidden(['created_at', 'updated_at'])->toArray(),null,200);
  }
}
