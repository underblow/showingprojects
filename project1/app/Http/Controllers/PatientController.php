<?php

namespace App\Http\Controllers;

use App\AffiliateTreatmentPath;
use App\CaseM;
use App\Patient;
use App\GroupUser;
use App\Affiliate;
use App\PatientRecord;
use App\Services\TextHelper;
use App\TreatmentPath;
use Auth;
use DB;
use Faker\Provider\Text;
use Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @Resource("Patients", uri="/v1/patients")
 *
 * @resource Patients
 */
class PatientController extends Controller
{
	public function __construct(\Dingo\Api\Http\Request $request)
	{
		$this->limit = $request->get('limit', 10);
		$this->offset = $request->get('offset', 0);
		$this->search = $request->get('search', '');
		//php artisan api:docs doesn't like search_by, so accept searchby
		$this->searchBy = $request->get('search_by', $request->get('searchby', ''));
		$this->direction = $request->get('direction', 'ASC');
		$this->sort = $request->get('sort', 'first_name');
		$this->isShowDeactivated = boolval($request->get('isShowDeactivated', false));
		$this->request = $request;

		$this->validate($request, [
			'sort' => Rule::in(['first_name', 'last_name', 'id_code', 'count_cases', 'birthdate', 'birth_country', 'birth_city', 'email', 'phone'])
		]);
	}

	/**
	 * Get patient.
	 *
	 * Get patient datas.
	 *
	 * @Get("/:id")
	 * @Versions({"v1"})
	 * @Request()
	 * @Transaction({
	 *      @Response(200, body={"id": 1,"name": "Normal Patient"}),
	 *      @Response(404, body={"message":"Patient not found"})
	 * })
	 *
	 * @return JSON user patient
	 */
	public function getPatient($id)
	{
		$user = Auth::user();

		$patient = Patient::find($id);

		if (!$patient) {
			return response()->error(TextHelper::t('Patient not found'), 404);
		}

		if ($patient->user_id != $user->id) {
			if(!$user->isGroupAdmin() || !$user->isUserInMyAdminGroups($patient->user_id)){
				return response()->error(TextHelper::t('You not owner a patient'), 403);
			}
		}

		return response()->success($patient);
	}

	/**
	 * Converts the search_by string to an array of database fields,
	 * throwing an exception if an impossible field is passed.
	 *
	 * @param string Comma-separated list of fields
	 * @return string[] Array of database fields like ['patients.first_name']
	 */
	private function searchByToFields($searchByString)
	{
		$parser = resolve('App\Services\SearchByParser');

		$parser->basicFields = [
			'patients.first_name',
			'patients.last_name',
			'patients.id_code',
			'patients.birthdate', 
			'patients.birth_country', 
			'patients.birth_city', 
			'patients.email', 
			'patients.phone'
		];
		$parser->combinedFields = [
			'patients.full_name' => [
				'patients.first_name',
				'patients.last_name',
        'patients.birthdate', 
        'patients.birth_country', 
        'patients.birth_city', 
        'patients.email', 
        'patients.phone'
			],
			'all' => $parser->basicFields
		];
		$parser->default = 'all';

		return $parser->searchByToFields($searchByString);
	}
	
	/**
	 * Returns user IDs after applying the the groupuserid and groupid filters.
	 *
	 * The users are guaranteed to be checked (if the user is not an admin, they
	 * won’t be able to see the group users).
	 *
	 * @return int[] user_ids
	 */
	private function getGroupUserIdsFilter() {
	    $user = Auth::user();

	    if (!$this->request->has('filter') || !$user->groups->count()) {
	        return [$user->id];
	    }

	    $inputFilter = $this->request->get('filter');

	    $allowedGroupIds = GroupUser::where('group_user.user_id',$user->id)
			->where('group_user.is_admin', 1)
			->pluck('group_id')->toArray();

	    if (!empty($inputFilter['groupid']) && $inputFilter['groupid'] != 'all') {
            $passedGroupIds = array_filter(array_map(function ($id) {
                    return intval(trim($id));
            }, explode(',', $inputFilter['groupid'])));
            
            $groupIds = array_intersect($passedGroupIds, $allowedGroupIds);
            
            if (!$groupIds) {
                $groupIds = $allowedGroupIds;
            }
	    }
	    else {
	        $groupIds = $allowedGroupIds;
	    }
	    
	    
	    if (!empty($inputFilter['groupuserid'])) {
	        if ($inputFilter['groupuserid'] == 'all') {
	            //get the users in the group
	            $userIds = GroupUser::whereIn('group_user.group_id', $groupIds)
	                ->pluck('user_id')->toArray();
	        }
	        else {
	            $passedUserIds = array_filter(array_map(function ($id) {
	                    return intval(trim($id));
	            }, explode(',', $inputFilter['groupuserid'])));
	            
	            if ($passedUserIds) {
	                $userIds = GroupUser::whereIn('group_user.user_id', $passedUserIds)
                        ->whereIn('group_user.group_id', $groupIds)
                        ->pluck('user_id')->toArray();
	            }
	            else {
	                //if empty list is given, filter by the user themselves
	                $userIds = [$user->id];
	            }
	        }
	    }
	    
	    return $userIds;
	}

	/**
	 * Get patients.
	 *
	 * Get patients datas.
	 *
	 * @Get("/")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("search", type="string", description="Search patient from: last name, first name, id patient", default="can be empty"),
	 *      @Parameter("searchby", type="string", description="Comma-separated list of: all, patients.first_name, patients.last_name, patients.full_name (combination of first_name and last_name), patients.id_code,patients.birthdate,patients.birth_country,patients.birth_city,patients.email,patients.phone; not used if search is empty", default="all"),
	 *      @Parameter("offset", type="integer", description="The page of results to view.", default=1),
	 *      @Parameter("limit", type="integer", description="The amount of results per page.", default=10),
	 *      @Parameter("sort", type="string", description="Name column for sorting [first_name,last_name,id_code,count_cases,birthdate,birth_country,birth_city,email,phone]", default="first_name"),
	 *      @Parameter("direction", type="string", description="Name column for sorting [desc,asc]", default="desc"),
	 *      @Parameter("isShowDeactivated", type="int", description="Show deactivated patients (1 shows all patients, 0 shows only active patients)", default=0),
	 *      @Parameter("filter[groupuserid]", type="string", description="Option for admin users; users whose filters should be shown ['all' or comma-separated list of user ids]", default="can be empty (current user’s ID)"),
	 *      @Parameter("filter[groupid]", type="string", description="Option for admin users; users from which groups can be shown ['all' or comma-separated list of group ids]", default="all"),
	 * })
	 * @Response(200, body={"data":{{"is_active": 1,"first_name": "Jack","last_name": "Man","id_code": "RM7N72","user_id": 1,"updated_at": "2017-04-12 12:17:58","created_at": "2017-04-12 12:17:58","id": 1,"case": "CaseObject", "count_cases":2},{"is_active": 1,"first_name": "Mark","last_name": "Man","id_code": "RM5N28","user_id": 1,"updated_at": "2017-04-12 12:17:58","created_at": "2017-04-12 12:17:58","id": 1,"case": "CaseObject", "count_cases":3}},"total":2})
	 *
	 * @return JSON patients details
	 */
	public function index(Request $request)
	{
		$user = Auth::user();

		$userIds = $this->getGroupUserIdsFilter();
		$prepareQueryPatient = Patient::with('countCases')->whereIn('patients.user_id', $userIds);

		if ($this->search) {
			$words = array_map('trim', explode(" ", $this->search));
			$searchFields = $this->searchByToFields($this->searchBy);

			foreach ($words as $keyword) {
				$prepareQueryPatient->where(function ($q) use ($keyword, $searchFields) {
					foreach ($searchFields as $searchField) {
						$q->orWhere($searchField, "LIKE", '%' . addcslashes($keyword, '%_') . '%');
					}
				});
			}
		}

		if (!$this->isShowDeactivated) {
			$prepareQueryPatient->where('patients.is_active', 1);
		}

		$prepareQueryPatient->limit($this->limit)->offset($this->offset);

		$prepareQueryPatientCount = clone $prepareQueryPatient;

		$total = $prepareQueryPatientCount->offset(0)->limit(1)->count();

		if ($this->sort === 'count_cases') {
			$prepareQueryPatient->selectRaw('patients.*, count(cases.patient_id) as `count_cases`')
				->leftJoin('cases', function ($join) {
				        $join->on('patients.id', '=', 'cases.patient_id');
				        $join->on('cases.is_deleted', '<>', DB::raw('1'));
				})
				//for strict mode https://github.com/laravel/framework/issues/14997
				->groupBy(DB::raw(implode(",", array_map(function ($a) {
					return "patients." . $a;
				}, Schema::getColumnListing('patients')))))
				->orderBy($this->sort, $this->direction);
		} else {
			$prepareQueryPatient->orderBy($this->sort, $this->direction);
		}

		$prepareQueryTotal = $prepareQueryPatient;

		$patients = $prepareQueryPatient->get(['patients.*']);

		$patients = $patients->toArray();

		foreach ($patients as $k => $patient) {
			$patients[$k]['count_cases'] = $patient['count_cases'] ? $patient['count_cases']['count'] : 0;
		}

		return response()->success($patients, $total);
	}

	/**
	 * Count patients.
	 *
	 * Count patients datas.
	 *
	 * @Get("/count")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("search", type="string", description="Search patient from: last name, first name, id patient", default="can be empty"),
	 *      @Parameter("searchby", type="string", description="Comma-separated list of: all, patients.first_name, patients.last_name, patients.full_name (combination of first_name and last_name), patients.id_code; not used if search is empty", default="all"),
	 *      @Parameter("isShowDeactivated", type="int", description="Show deactivated patients (1 shows all patients, 0 shows only active patients)", default=0),
	 *      @Parameter("filter[groupuserid]", type="string", description="Option for admin users; users whose filters should be shown ['all' or comma-separated list of user ids]", default="can be empty (current user’s ID)"),
	 *      @Parameter("filter[groupid]", type="string", description="Option for admin users; users from which groups can be shown ['all' or comma-separated list of group ids]", default="all"),
	 * })
	 * @Response(200, body={
	 *    "errors": false,
	 *    "data": {
	 *        "count": 3
	 *    }
	 * })
	 *
	 * @return JSON patients details
	 */
	public function getCount(Request $request)
	{
		$user = Auth::user();

		$userIds = $this->getGroupUserIdsFilter($request);
		$prepareQuery = Patient::with('countCases')->whereIn('patients.user_id', $userIds);

		if ($this->search) {
			$words = array_map('trim', explode(" ", $this->search));
			$searchFields = $this->searchByToFields($this->searchBy);
			foreach ($words as $keyword) {
				$prepareQuery->where(function ($q) use ($keyword, $searchFields) {
					foreach ($searchFields as $searchField) {
						$q->orWhere($searchField, "LIKE", '%' . addcslashes($keyword, '%_') . '%');
					}
				});
			}
		}
		if (!$this->isShowDeactivated) {
			$prepareQuery->where('patients.is_active', 1);
		}

		$count = $prepareQuery->offset(0)->limit(1)->count();

		return response()->success(compact('count'));
	}

	/**
	 * Delete a patient.
	 *
	 * Delete a patient.
	 *
	 * @Delete("/:id")
	 * @Versions({"v1"})
	 * @Request()
	 * @Response(204)
	 *
	 * @return JSON
	 */
	public function deletePatient($id)
	{
		$user = Auth::user();

		$patient = Patient::find($id);

		if (!$patient) {
			return response()->success(true, null, 204);
		}

		if ($patient->user_id != $user->id) {
			return response()->error(TextHelper::t('You not owner a patient'), 403);
		}
		
		if ($patient->is_active) {
		    return response()->error(TextHelper::t('Patient is active'), 422);
		}

		$patient->is_deleted = 1;
		$patient->save();

		return response()->success(true, null, 200);
	}

	/**
	 * Create new patient <b style='color:red'>(Deprecated)</b>
	 *
	 * <a style='color:red;text-decoration: underline;' href="#patients-v2">Use version 2.0</a><br/>Create new patient
	 *
	 * @Post("/")
	 * @Versions({"v1"})
	 * @Request({"first_name":"First Name","last_name":"Last name"})
	 * @Response(201,body={"is_active": 1,"first_name": "Mark","last_name": "Man","id_code": "RM5N28","user_id": 1,"updated_at": "2017-04-12 12:17:58","created_at": "2017-04-12 12:17:58","id": 1,"case": "CaseObject"})
	 *
	 * @return JSON
	 */
	public function postPatient(Request $request)
	{
		$user = Auth::user();

		$this->validate($request, [
			'first_name' => 'required|between:1,100',
			'last_name' => 'required|between:1,100',
		]);

		$affiliate = Affiliate::findOrFail($user->affiliate_id);

		//prepare id dode
		$digits = 3;

		do {
			$X = rand(0, 9);//rand(pow(10, $digits - 1), pow(10, $digits) - 1);
			$YYY = implode("", [chr(rand(65, 90)), chr(rand(65, 90)), chr(rand(65, 90))]);
			$id_code = substr($affiliate->abbreviation, 0, 2) . substr_replace($YYY, $X, 1, 0);
		} while (Patient::where('id_code', '=', $id_code)->first());

		$patient = new Patient();
		$patient['first_name'] = $request->first_name;
		$patient['last_name'] = $request->last_name;
		$patient['id_code'] = $id_code;
		$patient['user_id'] = $user->id;
		$patient->save();

		return response()->success($patient, null, 201);
	}

	/**
	 * Create patient record
	 *
	 * Create patient record
	 *
	 * @Post("/patientrecord")
	 * @Versions({"v1"})
	 * @Request({"patient_id":78,"case_id":223, "rebuild":1})
	 * @Response(201,body={
    "errors": false,
    "data": {
    "affiliate": {
    "id": 1,
    "name": "xxxx",
    "abbreviation": "XX",
    "phone": "xxx",
    "fax": "",
    "email": "xxx",
    "image": "",
    "website": "xxx"
    },
    "doctor": {
    "id": 5,
    "username": "testuser",
    "email": "xxx",
    "is_active": 1,
    "image": "",
    "title": "Dr.",
    "first_name": "Test",
    "last_name": "User",
    "address1": "",
    "address2": "",
    "city": "",
    "state": "",
    "zip": "",
    "primary_phone": "375336409506",
    "mobile_phone": "375336409506",
    "logo": ""
    },
    "doctor_public_contacts": {},
    "patient": {
    "id": 78,
    "id_code": "RMR5GC",
    "first_name": "xxx",
    "last_name": "xxx",
    "birthdate": 1510704000,
    "birth_country": "US",
    "birth_city": "",
    "email": "xxx",
    "phone": ""
    },
    "clinical_path": {
    "id": 93,
    "name": "CP 3",
    "is_local_affiliate": 0
    },
    "treatment_path": {
    "id": 118,
    "name": "tp 3_1",
    "user_id": 5,
    "is_local_affiliate": 0,
    "schedule_updated_at": 1512470845,
    "schedule_updated_by": {
    "id": 5,
    "first_name": "Test",
    "last_name": "User",
    "title": "Dr."
    },
    "default_assignee_id": 0,
    "count_cases": 3
    },
    "case": {
    "id": 223,
    "id_code": "RMA7OKTU004",
    "status": 0,
    "schedule_updated_at": 1512470875,
    "schedule_updated_by": {
    "id": 5,
    "first_name": "Test",
    "last_name": "User",
    "title": "Dr.",
    "logo": "",
    "image": ""
    },
    "created_date": 1512470875
    },
    "case_date": 1512470875,
    "case_date_at": {
    "date": "2017-12-05 10:47:55.000000",
    "timezone_type": 3,
    "timezone": "UTC"
    },
    "patient_record_list": {
    {
    "step_id": 238,
    "step_order": 0,
    "survey": {
    "id": 1427,
    "name": "Demo Survey 1",
    "description": "",
    "is_local": 1
    },
    "task_id": 1338,
    "task_status": 2,
    "task_complete_date": null,
    "questions": {
    {
    "id": 7376,
    "name": "Demo Question",
    "url": "",
    "section_title": "",
    "description": "123",
    "use_for_graph": 0,
    "patient_description": "Some QUESTION patient description",
    "subquestions": {
    {
    "id": 37975,
    "text": "#1 SQ",
    "type": "Radio group",
    "answers": {
    "values": {
    "Yes",
    "No",
    "1"
    },
    "default": null
    },
    "description": "",
    "parent_id": 37971,
    "patient_description": "Some patient description goes here",
    "order": 0,
    "identifier": "",
    "origin_type": "Radio group",
    "selected_answer": {
    "value": {
    "No",
    7
    }
    },
    "benchmark_answer_min": 7,
    "benchmark_answer_max": 9,
    "benchmark_answer_count_analyzed": 2,
    "sampleSize": 2,
    "files": {},
    "benchmark_answer": {
    "No (8)"
    },
    "benchmark_sample_size": 2,
    "surveys_who_have_digit_similar_data": {
    {
    "survey_id": 1426,
    "value": 6
    },
    {
    "survey_id": 1427,
    "value": 7
    },
    {
    "survey_id": 1428,
    "value": 9
    }
    },
    "surveys_who_have_similar_data": {
    1427,
    1428
    },
    "additionalUserAnswer": 7,
    "currentUserAnswer": {
    "displayName": "No (7)",
    "rawValue": "No"
    },
    "currentDatabaseAnswer": {
    "displayName": "No (8)",
    "rawValue": "No"
    },
    "additionalDatabaseAnswer": 8,
    "benchmarkValue": 8
    },
    {
    "id": 37976,
    "text": "#2 SQ",
    "type": "Dropdown",
    "answers": {
    "values": {
    "choice1",
    "choice2",
    "choice3"
    },
    "default": null
    },
    "description": "",
    "parent_id": 37972,
    "patient_description": "",
    "order": 1,
    "identifier": "",
    "origin_type": "Dropdown",
    "selected_answer": {
    "value": {
    "choice1"
    }
    },
    "benchmark_answer_min": 0,
    "benchmark_answer_max": 0,
    "benchmark_answer_count_analyzed": 3,
    "sampleSize": 2,
    "files": {},
    "benchmark_answer": {
    "choice1"
    },
    "benchmark_sample_size": 2,
    "surveys_who_have_digit_similar_data": null,
    "surveys_who_have_similar_data": {
    1426,
    1427,
    1428
    },
    "currentUserAnswer": {
    "displayName": "choice1",
    "rawValue": "choice1"
    },
    "currentDatabaseAnswer": {
    "displayName": "choice1",
    "rawValue": "choice1"
    }
    }
    },
    "files": {}
    }
    }
    }
    }
    }
    })
	 * @return JSON
	 */
	public function postPatientRecord(Request $request)
	{
		$user = Auth::user();

		$this->validate($request, [
			'patient_id' => 'required|exists:patients,id',
			'case_id' => 'required|exists:cases,id',
			'rebuild' => 'required|boolean',
		]);

		$case = CaseM::find($request->case_id);
		if ($case && $case->is_deleted) {
		    $case = null;
		}

		return response()->success(PatientRecord::getRecordData($case->user_id, $request->patient_id, $request->case_id, $request->rebuild), null, 201);
	}


	/**
	 * Get patient record status
	 *
	 * Get patient record status
	 *
	 * @Post("/patientrecordstatus")
	 * @Versions({"v1"})
	 * @Request({"patient_id":1,"case_id":1})
	 * @Response(201,body={"errors": false,"data": {"status": 0}})
	 *
	 * @return JSON
	 */
	public function postPatientRecordStatus(Request $request)
	{
		$user = Auth::user();

		$this->validate($request, [
			'patient_id' => 'required|exists:patients,id',
			'case_id' => 'required|exists:cases,id',
		]);

		return response()->success(['status' => PatientRecord::getStatus($user->id, $request->patient_id, $request->case_id)], null, 201);
	}

	/**
	 * Update patient <b style='color:red'>(Deprecated)</b>
	 *
	 * <a style='color:red;text-decoration: underline;' href="#patients-v2">Use version 2.0</a><br/>Update patient.
	 *
	 * @Patch("/patients/:id")
	 * @Versions({"v1"})
	 * @Request({"first_name": "Man","last_name": "Manovich"})
	 * @Response(200,body={"id": 1,"id_code": "RMB3IU","first_name": "xxx","last_name": "Sam","user_id": 7,"is_active": 1})
	 *
	 * @return JSON patient
	 */
	public function patchPatients($id, Request $request)
	{
		$user = Auth::user();

		$this->validate($request, [
			'first_name' => 'required',
			'last_name' => 'required',
		]);

		$patient = Patient::find($id);

		if (!$patient) {
			return response()->error(TextHelper::t('Patient not found'), 404);
		}

		if ($patient->user_id != $user->id) {
			return response()->error(TextHelper::t('You not owner a patient'), 403);
		}

		$patient->first_name = $request['first_name'];
		$patient->last_name = $request['last_name'];
		$patient->save();

		return response()->success($patient->makeHidden(['created_at', 'updated_at'])->toArray(), null, 200);
	}

	/**
	 * Deactivate patient
	 *
	 * Deactivate patient.
	 *
	 * @Patch("/patients/:id/deactivate")
	 * @Versions({"v1"})
	 * @Request({"is_active": 0})
	 * @Response(200,body={"id": 1,"id_code": "RMB3IU","first_name": "xxx","last_name": "Sam","user_id": 7,"is_active": 0})
	 *
	 * @return JSON patient
	 */
	public function patchDeactivate($id, Request $request)
	{
		$user = Auth::user();

		$this->validate($request, [
			'is_active' => 'required|boolean',
		]);

		$patient = Patient::find($id);

		if (!$patient) {
			return response()->error(TextHelper::t('Patient not found'), 404);
		}

		if ($patient->user_id != $user->id) {
			return response()->error(TextHelper::t('You not owner a patient'), 403);
		}

		$patient->is_active = $request['is_active'];
		$patient->save();

		return response()->success($patient->makeHidden(['created_at', 'updated_at'])->toArray(), null, 200);
	}

	/**
	 * Get cases of patient
	 *
	 * Get cases of patient
	 *
	 * @Get("/:id/cases")
	 * @Versions({"v1"})
	 * @Response(201,body={"id":3,"id_code":"RMV2CZAZ001","treatment_path_id":7,"patient_id":3,"is_active":1,"step_id":15,"modified_by":1,"status":0,"schedule_updated_at":null,"schedule_updated_by":null,"created_date":1501329313,"created_by":{"id":9,"first_name":"Alexandr","last_name":"Zhuk","title":"Mr.","logo":null,"image":null,"affiliate_id":1,"affiliate":{"id":1,"name":"xxx","image":"","abbreviation":"RM"}}})
	 * @Parameters({
	 *      @Parameter("offset", type="integer", description="The page of results to view.", default=1),
	 *      @Parameter("limit", type="integer", description="The amount of results per page.", default=10),
	 * })
	 * @return JSON
	 */
	public function getPatientCases($id, Request $request)
	{
		$user = Auth::user();

		$cases = CaseM::with(['createdBy.affiliate'])->where('patient_id', $id)->where('cases.is_deleted', '<>', 1)->orderBy('created_at')->limit($this->limit)->offset($this->offset)->get();

		foreach ($cases as $case) {
			$case->createdBy->makeHidden('affiliate_id');
		}

		return response()->success($cases, null, 200);
	}
}
