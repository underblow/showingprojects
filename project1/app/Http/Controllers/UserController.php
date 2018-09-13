<?php

namespace App\Http\Controllers;

use App\Services\TextHelper;
use App\User;
use App\GroupUser;
use App\Task;
use Auth;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;
use Faker\Provider\Text;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Input;
use Validator;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationException;

/**
 * @Resource("Users", uri="/v1/users")
 *
 * @resource Users
 */
class UserController extends Controller
{
	public function __construct(\Dingo\Api\Http\Request $request)
	{
		$this->limit = $request->get('limit', 10);
		$this->offset = $request->get('offset', 0);
		$this->direction = $request->get('direction', 'ASC');
		$this->search = $request->get('search', '');
		$this->searchBy = $request->get('search_by', $request->get('searchby', ''));
		$this->respectGroup = boolval($request->get('respectGroup', true));
		$sort = $request->get('sort', 'id');

		if($sort == 'position') {
			$sort = 'position.name';
		}

		$this->sort = $sort;

		$this->validate($request, [
			'sort' => Rule::in([
				'id',
				'address1',
				'address2',
				'birthday',
				'city',
				'email',
				'first_name',
				'full_name',
				'last_name',
				'mobile_phone',
				'position',
				'primary_phone',
				'state',
				'title',
				'username',
				'zip',
			])
		]);
	}

	/**
	 * Get user current context.
	 *
	 * Get user datas.
	 *
	 * @Resource("Users", uri="/users")
	 * @Get("/me")
	 * @Versions({"v1"})
	 * @Request()
	 * @Response(200, body={"id":1,"username":"admin","email":"admin@example.com","is_active":1,"image":null,"email_verified":"1","email_verification_code":null,"created_at":"2017-06-21 14:39:11","updated_at":"2017-08-04 10:40:24","affiliate_id":1,"is_public_basic":0,"is_public_contact":0,"is_public_job":0,"title":"Mr.","first_name":"Admin","last_name":"Admin","birthday":"","address1":"","address2":"","city":"","state":"","zip":"","primary_phone":"","mobile_phone":"","token_ttl_min":31,"logo":null,"position":"Administrator","affiliate_name":"xxx","affiliate":{"id":1,"name":"Regen Med","image":"","abbreviation":"RM"}})
	 *
	 * @return JSON user details and auth credentials
	 */
	public function getMe()
	{
		$user = Auth::user();

		return $this->getUser($user->id);
	}

	/**
	 * Get user context.
	 *
	 * Get user datas.
	 *
	 * @Get("/:id")
	 * @Versions({"v1"})
	 * @Request()
	 * @Response(200, body={"id":1,"username":"admin","email":"admin@example.com","is_active":1,"image":null,"email_verified":"1","email_verification_code":null,"created_at":"2017-06-21 14:39:11","updated_at":"2017-08-04 10:40:24","affiliate_id":1,"is_public_basic":0,"is_public_contact":0,"is_public_job":0,"title":"Mr.","first_name":"Admin","last_name":"Admin","birthday":"","address1":"","address2":"","city":"","state":"","zip":"","primary_phone":"","mobile_phone":"","token_ttl_min":31,"logo":null,"position":"Administrator","affiliate_name":"Regen Med","affiliate":{"id":1,"name":"Regen Med","image":"","abbreviation":"RM"}})
	 *
	 * @return JSON user details and auth credentials
	 */
	public function getUser($id)
	{
		$user_details = User::with(["affiliate.addresses","positions"])->find($id);

		$user_details['position'] =  $user_details->positions[0]->name ?? null;
		$user_details['affiliate_name'] =  $user_details->affiliate->name ?? null;
		$user_details['affiliate'] =  $user_details->affiliate;
		$user_details['is_group_admin'] =  (int)$user_details->isGroupAdmin();
		$user_details['is_group_member'] =  (int)$user_details->isGroupMember();

		return response()->success($user_details);
	}

	/**
	 * Converts the search_by string to an array of database fields,
	 * throwing an exception if an impossible field is passed.
	 *
	 * @param string Comma-separated list of fields
	 * @return string[] Array of database fields like ['users.first_name']
	 */
	private function searchByToFields($searchByString)
	{
		$parser = resolve('App\Services\SearchByParser');

		$parser->basicFields = [
			'users.first_name',
			'users.last_name'
		];
		$parser->combinedFields = [
			'users.full_name' => [
				'users.first_name',
				'users.last_name'
			],
			'all' => $parser->basicFields
		];
		$parser->default = 'all';

		return $parser->searchByToFields($searchByString);
	}

	/**
	 * Get assignee list
	 *
	 * Get all users who have at least one case assigned
	 *
	 * @Get("/assigned")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("sort", type="string", description="Name column for sorting <br/>[id,<br/>address1,<br/>address2,<br/>birthday,<br/>city,<br/>email,<br/>first_name,<br/>full_name,<br/>last_name,<br/>mobile_phone,<br/>position,<br/>primary_phone,<br/>state,<br/>title,<br/>username,<br/>zip]", default="id"),
	 *      @Parameter("direction", type="string", description="Name column for sorting [desc,asc]", default="asc"),
	 *      @Parameter("offset", type="integer", description="The page of results to view.", default=1),
	 *      @Parameter("limit", type="integer", description="The amount of results per page.", default=10),
	 *      @Parameter("search", type="string", description="Search case in fields determined by searchby", default="can be empty"),
	 *      @Parameter("searchby", type="string", description="Comma-separated list of: all, users.first_name, users.last_name, users.full_name (combination of first_name and last_name); not used if search is empty", default="all"),
	 * })
	 * @Response(200,body={"errors":false,"data":{{"id":1,"username":"admin","email":"admin@example.com","is_active":1,"image":null,"email_verified":"1","email_verification_code":null,"created_at":"2017-06-21 14:39:11","updated_at":"2017-07-31 15:08:27","affiliate_id":1,"is_public_basic":0,"is_public_contact":0,"is_public_job":0,"title":"Mr.","first_name":"Admin","last_name":"Admin","birthday":"","address1":"","address2":"","city":"","state":"","zip":"","primary_phone":"","mobile_phone":"","token_ttl_min":60,"affiliate":{"id":1,"name":"Regen Med"},"positions":{{"name":"Administrator"}}}}})
	 *
	 * @return JSON
	 */
	public function getAssigneed() {
	    $user = Auth::user();

	    $assigneeIds = Task::distinct()->select(['assignee_id'])
	        ->get()->pluck('assignee_id')->toArray();

	    $query = User::with(["affiliate","positions"])
	        ->whereIn('users.id', $assigneeIds);
	    $this->postprocessUsersQuery($query);

        if ($this->search) {
            $words = array_map('trim', explode(" ", $this->search));
            $searchFields = $this->searchByToFields($this->searchBy);

            foreach ($words as $keyword) {
                $query->where(function($q) use($keyword, $searchFields) {
                    foreach ($searchFields as $searchField) {
                        $q->orWhere($searchField, "LIKE", '%' . addcslashes($keyword, '%_'). '%');
                    }
                });
            }
        }
	    $data = $query->get();
	    return response()->success($data);
	}
	
	/**
	 * Count assignees
	 *
	 * Get the count of the users who have at least one case assigned
	 *
	 * @Get("/assigned/count")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("search", type="string", description="Search case in fields determined by searchby", default="can be empty"),
	 *      @Parameter("searchby", type="string", description="Comma-separated list of: all, users.first_name, users.last_name, users.full_name (combination of first_name and last_name); not used if search is empty", default="all"),
	 * })
	 * @Response(200,body={
	 *    "errors": false,
	 *    "data": {
	 *        "count": 1
	 *    }
	 * })
	 *
	 * @return JSON
	 */
	public function getAssigneedCount() {
	    $user = Auth::user();

	    $assigneeIds = Task::distinct()->select(['assignee_id'])
	        ->get()->pluck('assignee_id')->toArray();

	    $query = User::with(["affiliate","positions"])
	        ->whereIn('users.id', $assigneeIds);

        if ($this->search) {
            $words = array_map('trim', explode(" ", $this->search));
            $searchFields = $this->searchByToFields($this->searchBy);
            
            foreach ($words as $keyword) {
                $query->where(function($q) use($keyword, $searchFields) {
                    foreach ($searchFields as $searchField) {
                        $q->orWhere($searchField, "LIKE", '%' . addcslashes($keyword, '%_'). '%');
                    }
                });
            }
        }
	    $count = $query->count();
	    return response()->success(compact('count'));
	}

	/**
	 * Get assignor list
	 *
	 * Get all users who who are assignors for at least one case
	 *
	 * @Get("/assignors")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("sort", type="string", description="Name column for sorting <br/>[id,<br/>address1,<br/>address2,<br/>birthday,<br/>city,<br/>email,<br/>first_name,<br/>full_name,<br/>last_name,<br/>mobile_phone,<br/>position,<br/>primary_phone,<br/>state,<br/>title,<br/>username,<br/>zip]", default="id"),
	 *      @Parameter("direction", type="string", description="Name column for sorting [desc,asc]", default="asc"),
	 *      @Parameter("offset", type="integer", description="The page of results to view.", default=1),
	 *      @Parameter("limit", type="integer", description="The amount of results per page.", default=10),
	 *      @Parameter("search", type="string", description="Search case in fields determined by searchby", default="can be empty"),
	 *      @Parameter("searchby", type="string", description="Comma-separated list of: all, users.first_name, users.last_name, users.full_name (combination of first_name and last_name); not used if search is empty", default="all"),
	 * })
	 * @Response(200,body={"errors":false,"data":{{"id":1,"username":"admin","email":"admin@example.com","is_active":1,"image":null,"email_verified":"1","email_verification_code":null,"created_at":"2017-06-21 14:39:11","updated_at":"2017-07-31 15:08:27","affiliate_id":1,"is_public_basic":0,"is_public_contact":0,"is_public_job":0,"title":"Mr.","first_name":"Admin","last_name":"Admin","birthday":"","address1":"","address2":"","city":"","state":"","zip":"","primary_phone":"","mobile_phone":"","token_ttl_min":60,"affiliate":{"id":1,"name":"Regen Med"},"positions":{{"name":"Administrator"}}}}})
	 *
	 * @return JSON
	 */
	public function getAssignors() {
	    $user = Auth::user();

	    $assignorIds = Task::distinct()->select(['assignor_id'])
	        ->get()->pluck('assignor_id')->toArray();

	    $query = User::with(["affiliate","positions"])
	        ->whereIn('users.id', $assignorIds);
	    $this->postprocessUsersQuery($query);

        if ($this->search) {
            $words = array_map('trim', explode(" ", $this->search));
            $searchFields = $this->searchByToFields($this->searchBy);

            foreach ($words as $keyword) {
                $query->where(function($q) use($keyword, $searchFields) {
                    foreach ($searchFields as $searchField) {
                        $q->orWhere($searchField, "LIKE", '%' . addcslashes($keyword, '%_'). '%');
                    }
                });
            }
        }
	    $data = $query->get();
	    return response()->success($data);
	}

	/**
	 * Count assignor
	 *
	 * Get the count of the users who are assignors for at least one case
	 *
	 * @Get("/assignors/count")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("search", type="string", description="Search case in fields determined by searchby", default="can be empty"),
	 *      @Parameter("searchby", type="string", description="Comma-separated list of: all, users.first_name, users.last_name, users.full_name (combination of first_name and last_name); not used if search is empty", default="all"),
	 * })
	 * @Response(200,body={
	 *    "errors": false,
	 *    "data": {
	 *        "count": 1
	 *    }
	 * })
	 *
	 * @return JSON
	 */
	public function getAssignorsCount() {
	    $user = Auth::user();

	    $assignorIds = Task::distinct()->select(['assignor_id'])
	        ->get()->pluck('assignor_id')->toArray();

	    $query = User::with(["affiliate","positions"])
	        ->whereIn('users.id', $assignorIds);

        if ($this->search) {
            $words = array_map('trim', explode(" ", $this->search));
            $searchFields = $this->searchByToFields($this->searchBy);

            foreach ($words as $keyword) {
                $query->where(function($q) use($keyword, $searchFields) {
                    foreach ($searchFields as $searchField) {
                        $q->orWhere($searchField, "LIKE", '%' . addcslashes($keyword, '%_'). '%');
                    }
                });
            }
        }
	    $count = $query->count();
	    return response()->success(compact('count'));
	}

	/**
	 * New User Profile Pic.
	 *
	 * Upload new user profile pic and delete previous if existed. The posted
	 * file should be named ‘file’.
	 *
	 * @Post("/profilepic")
	 * @Versions({"v1"})
	 * @Request(contentType="multipart/form-data")
	 * @Transaction({
	 *      @Response(200, body={"message":"success"}),
	 *      @Response(404, body={"message":"Error loading profile picture"})
	 * })
	 *
	 * @return JSON affiliate_id
	 */
	public function uploadProfilePic(Request $request)
	{
		$this->validate($request, [
			'file' => 'image|max:2048|dimensions:min_width=25,min_height=25,max_width=1080,max_height=1080',
		]);

		$file = $request->file('file');

		if ($request->hasFile('file')) {
			$this->handleFileUpload($file, 'image');

			return response()->success(TextHelper::t('success'), 201);
		} else {
			return response()->error(TextHelper::t('Error loading profile picture'));
		}
	}
	
	/**
	 * Delete User Profile Pic.
	 *
	 * Delete the user profile pic if existed.
	 *
	 * @Delete("/profilepic")
	 * @Versions({"v1"})
	 * @Transaction({
	 *      @Response(200, body={"message":"success"}),
	 *      @Response(404, body={"message":"Error deleting profile picture"})
	 * })
	 *
	 * @return JSON affiliate_id
	 */
	public function deleteProfilePic(Request $request)
	{
		$user = Auth::user();
		$user = User::find($user->id); //not sure if this is needed?
		
		if ($user->image) {
            if ($user->image && Storage::disk('ftp_admin')->exists($user->image)) {
                Storage::disk('ftp_admin')->deleteDirectory(explode("/",$user->image)[0]);
            }
            $user->image = null;
            $user->save();
            return response()->success(TextHelper::t('success'), 201);
        }
        else {
            return response()->error(TextHelper::t('Error deleting profile picture'));
        }
	}

	/**
	 * New User Profile Pic <b style='color:red'>(Deprecated)</b>
	 *
	 * Please, use <a style='color:red;text-decoration: underline;' href="#users-post">/profilepic</a><br/> instead.
	 * This function is deprecated because it is misnamed: its name suggests
	 * that it should be uploading a logo, but instead it upload a profile
	 * picture. The error message is likewise misnamed.
	 *
	 * Upload new User profile picture and delete previous if existed. The
	 * posted file should be named ‘file’.
	 *
	 * @Post("/logo")
	 * @Versions({"v1"})
	 * @Request(contentType="multipart/form-data")
	 * @Transaction({
	 *      @Response(200, body={"message":"success"}),
	 *      @Response(404, body={"message":"Error loading logo"})
	 * })
	 *
	 * @return JSON affiliate_id
	 */
	public function uploadProfilePicDeprecated(Request $request)
	{
		$this->validate($request, [
			'file' => 'image|max:2048|dimensions:min_width=25,min_height=25,max_width=1080,max_height=1080',
		]);

		$file = $request->file('file');

		if ($request->hasFile('file')) {
			$this->handleFileUpload($file, 'image');

			return response()->success(TextHelper::t('success'), 201);
		} else {
			return response()->error(TextHelper::t('Error loading logo'));
		}
	}

	/**
	 * New User Logo.
	 *
	 * Upload new User Logo and delete previous if existed. The posted file
	 * should be named ‘file’.
	 *
	 * @Post("/logopic")
	 * @Versions({"v1"})
	 * @Request(contentType="multipart/form-data")
	 * @Transaction({
	 *      @Response(200, body={"message":"success"}),
	 *      @Response(404, body={"message":"Error loading logo"})
	 * })
	 *
	 * @return JSON affiliate_id
	 */
	public function uploadLogo(Request $request)
	{
		$this->validate($request, [
			'file' => 'image|max:1024|dimensions:min_width=200,min_height=200',
		]);

		$file = $request->file('file');

		if ($request->hasFile('file')) {
		    $this->handleFileUpload($file, 'logo');

			return response()->success(TextHelper::t('success'), 201);
		} else {
			return response()->error(TextHelper::t('Error loading logo'));
		}
	}
	

	/**
	 * Delete User Logo.
	 *
	 * Delete the user logo if existed.
	 *
	 * @Delete("/logopic")
	 * @Versions({"v1"})
	 * @Transaction({
	 *      @Response(200, body={"message":"success"}),
	 *      @Response(404, body={"message":"Error deleting logo"})
	 * })
	 *
	 * @return JSON affiliate_id
	 */
	public function deleteLogo(Request $request)
	{
		$user = Auth::user();
		$user = User::find($user->id); //not sure if this is needed?
		
		if ($user->logo) {
            if ($user->logo && Storage::disk('ftp_admin')->exists($user->logo)) {
                Storage::disk('ftp_admin')->deleteDirectory(explode("/",$user->logo)[0]);
            }
            $user->logo = null;
            $user->save();
            return response()->success(TextHelper::t('success'), 201);
        }
        else {
            return response()->error(TextHelper::t('Error deleting logo'));
        }
	}

	/**
	 * Handles the upload of user picture of a given type (currently
	 * 'logo' and 'image' aka profile pic).
	 * @param object File object as returned by Request
	 * @param string Name of the string property in the user object.
	 */
	private function handleFileUpload($file, $fileType) {
	    $user = Auth::user();

        $extension = $file->getClientOriginalExtension();
        $directory = sha1(time() . $fileType);
        Storage::disk('ftp_admin')->makeDirectory($directory);
        $fileName = sha1(time() . time()) . ".{$extension}";
        Storage::disk('ftp_admin')->put($directory . '/' . $fileName, file_get_contents($file->getRealPath()));

        $user = User::find($user->id);
        if ($user->$fileType && Storage::disk('ftp_admin')->exists($user->$fileType)) {
            Storage::disk('ftp_admin')->deleteDirectory(explode("/",$user->$fileType)[0]);
        }
         $user->$fileType = $directory . '/' . $fileName;
         $user->save();
	}
	
	/**
	 * Converts phone format to a PREG regular expression.
	 *
	 * @param string Phone format
	 * @return String regular expression
	 */
	private static function phoneFormatToRegexp($format)
	{
	    return '^' . preg_replace_callback('/(([a-zA-Z])|(\#)|([^a-zA-Z#]+))/',
            function ($matches) {
                if ($matches[2]) {
                    return '\\d';
                }
                elseif ($matches[3]) {
                    return '\\#';
                }
                else {
                    return preg_quote($matches[4]);
                }
            }, $format) . '$';
	}
	
	/**
	 * Checks that the phone number is in the correct format for the country.
	 *
	 * @param string Country name
	 * @param string Phone number
	 */
	private function phoneFormatIsValid($country, $phone)
	{
	    if (!$phone) {
	        //Empty phone numbers are always valid
	        return true;
	    }
	    
        $countryData = DB::table('dictionaries')
            ->where(['group' => 'country',
                    'value' => $country])->first();
        if (!$countryData) {
                return response()->error(TextHelper::t('Invalid contry data set for the country ‘{country}‘', ['country' => $country]));
        }
        
        $format = $countryData->data;
        
        //convert 1-xxx-yyy-yyyy into a regular expression, replacing [a-zA-Z]
        //with \d and escaping everything else
        $formatRegexp = '#' . self::phoneFormatToRegexp($format) . '#';
        
        return boolval(preg_match($formatRegexp, $phone));
	}

	/**
	 * Update current user.
	 *
	 * Update the data for the current user.
	 *
	 * Re-setting affiliate_id RESETS the list of possible states and phone
	 * formats (unless the affiliate is the same country as the previous
	 * affiliate). 
	 *
	 * The state field should be a valid state. You can get the list of the
	 * valid states for the user using the /users/me/states endpoint. The states
	 * are only available when the user’s affiliate has a mailing address set
	 * up.
	 *
	 * The primary_phone and mobile_phone should follow the valid format. You
	 * can get the format valid for the current user using /users/me/formats.
	 * The formats are available only when the user’s affiliate has a mailing
	 * address set up.
	 *
	 * The title field should be one of the available titles. You can get the
	 * list of the available titles using /users/titles
	 *
	 * @Patch("/me")
	 * @Versions({"v1"})
	 * @Request({
	 *   "title": "Dr.",
	 *   "first_name": "FirstName",
	 *   "last_name": "LastName",
	 *   "email": "email@example.org",
	 *   "birthday_timestamp": 626659200,
	 *   "address1": "Address 1",
	 *   "address2": "Address 2",
	 *   "city": "City",
	 *   "state": "State",
	 *   "zip": "458555",
	 *   "primary_phone": "458555",
	 *   "mobile_phone": "458555",
	 *   "affiliate_id": 123,
	 *   "position_id": 123,
	 *   "is_public_basic": 0,
	 *   "is_public_contact": 0,
	 *   "is_public_job": 0,
	 * })
	 * @Response(200,body={
	 *    "errors": false,
	 *    "data": {
	 *        "id": 1,
	 *        "username": "admin",
	 *        "email": "admin@example.com",
	 *        "is_active": 1,
	 *        "image": "/uploads/user/3ce0c02f6b3480f3049200d02759dcee1e391bcb/cb9ae7ea2ac63da8a4c8958fa3d9a69fd22e4491.jpg",
	 *        "email_verified": "1",
	 *        "email_verification_code": null,
	 *        "created_at": "2017-06-21 14:39:11",
	 *        "updated_at": "2017-08-08 12:42:52",
	 *        "affiliate_id": 1,
	 *        "is_public_basic": 0,
	 *        "is_public_contact": 0,
	 *        "is_public_job": 0,
	 *        "title": "Mr.",
	 *        "first_name": "Admin",
	 *        "last_name": "Admin",
	 *        "birthday": "11/10/1989",
	 *        "address1": "",
	 *        "address2": "",
	 *        "city": "",
	 *        "state": "",
	 *        "zip": "",
 	 *       "primary_phone": "",
	 *         "mobile_phone": "",
	 *        "token_ttl_min": 300,
	 *        "logo": "/uploads/user/7c5c01f8eff467a7ab41fa791027ea7770a7d97a/5ff9a09f4c8811bf46e3d71ac12a0c3c5eb6897d.jpg",
	 *        "position": "Administrator",
	 *        "affiliate_name": "Regen Med",
	 *        "birthday_timestamp": 626659200
	 *    }
	 *})
	 *
	 * @return JSON with the updated user object
	 */
	public function patchMe(Request $request)
	{
	    $user = Auth::user();
	    
	    
		$this->validate($request, [
		    'title' => 'filled',
		    'first_name' => 'min:3',
		    'last_name' => 'filled',
		    'email' => 'email|unique:users,email,' . $user->id,
		    //'birthday', //format: 'MM/dd/yyyy'
		    'birthday_timestamp' => '',
		    'address1',
		    'address2',
		    'city',
		    'state',
		    'zip',
		    'primary_phone',
		    'mobile_phone',
		    'affiliate_id' => 'exists:affiliates,id',
		    'position_id' => 'exists:positions,id',
		    'is_public_basic' => 'boolean',
		    'is_public_contact' => 'boolean',
		    'is_public_job' => 'boolean'
		]);
		
		if($request->has('title')) {
		    $title = $request->get('title');
		    $hasTitle = DB::table('dictionaries')
                            ->where(['group' => 'user_title',
                                    'value' => $title])
                            ->count();
            if (!$hasTitle) {
                return response()->error($title . ' is not a valid title.', 422);
            }
            
            $user->title = $title;
		}
		
		foreach (['first_name', 'last_name', 'email', 'address1', 'address2',
		          'city', 'zip', 'affiliate_id',
		          'is_public_basic', 'is_public_contact',
		          'is_public_job'] as $field) {
		    if ($request->has($field) || $request->exists($field)) {
		        $user->$field = $request->get($field);
		    }
		}
		
		if ($request->has('birthday')) {
		    return response()->error(TextHelper::t('The field birthday is a legacy field that is not supposed to be used by client applications. Use birthday_timestamp instead'), 422);
		}
		
		if($request->has('birthday_timestamp')) {
		    $birthday_timestamp = $request->get('birthday_timestamp');
		    if ($birthday_timestamp) {
		        $birthday = Carbon::createFromTimestamp($birthday_timestamp);
		        $user->birthday = $birthday->format('m/d/Y');
		    }
		    else {
		        $user->birthday = null;
		    }
		}
		
		$userCountryRow = DB::table('affiliates_addresses')
		    ->where(['affiliate_id' => $user->affiliate_id, 'type' => 'mailing'])
		    ->select('country')->first();
		if ($userCountryRow) {
		    //we can only set some fields if we know the country
		    $country = $this->normalizeCountry($userCountryRow->country);
		    
            if ($request->has('state')) {
                $state = $request->get('state');
                $stateIsValid = DB::table('dictionaries')
                            ->where(['group' => 'state_' . strtolower($country),
                                    'value' => $state])
                            ->count();
                if ($stateIsValid) {
                    $user->state = $state;
                }
                else {
                    return response()->error(TextHelper::t('Impossible state ‘{state}’ for the country ‘{country}’', ['state' => $state, 'country' => $country]), 422);
                }
            }
            
            foreach (['primary_phone', 'mobile_phone'] as $phoneType) {
                if ($request->has($phoneType)) {
                    $phone = $request->get($phoneType);
                    $hasInvalidChars = preg_match('/([^0-9])/', $phone);
                    if ($hasInvalidChars || strlen($phone) > 21) {
                        return response()->error(TextHelper::t('The phone number ‘{phone}’ does not match the phone format.', ['phone' => $phone]), 422);
                    }
	                /*
                    if (!$this->phoneFormatIsValid($country, $phone)) {
                        return response()->error('The phone number ‘' . $phone 
                            . '’ does not match the phone format.', 422);
                    }
                    */
                    $user->$phoneType = $phone;
                }elseif($request->exists($phoneType)){
					$user->$phoneType = "";
				}
            }
        }
        else {
            foreach (['state', 'primary_phone', 'mobile_phone'] as $field) {
                if ($request->has($field)) {
                    $value = $request->get($field);
                    if ($value) {
                        //without states, we can’t set some fields
                        return response()->error(TextHelper::t('The user is assigned to an affiliate without a mailing address, so we don’t know the user’s country and therefore don’t know the available states and phone formats'), 422);
                    }
                    else {
                        //but we can unset anything
                        $user->$field = null;
                    }
                }
            }
        }
        
        $user->save();
        
        if ($request->has('position_id')) {
            $positionId = $request->get('position_id');
            //Check if the position is valid for the given affiliate
            $positionIsValid = DB::table('positions')
                ->where('id', $positionId)
                ->whereIn('affiliate_id', [$user->affiliate_id, 0])
                ->count();
            if (!$positionIsValid) {
                    return response()->error(TextHelper::t('Impossible position_id'), 422);
            }
            $user->positions()->detach();
            $user->positions()->attach($positionId);
        }
        
        return $this->getMe();


	    /*
	    [x] Title			title
        [x] First Name		first_name
        [x] Last Name		last_name
        [x] Email			email
            Contact info
        [x] Birthday		birthday
        Address 1		address1
        Address 2		address2
        City			city
        State			state
        Mailing ZIP		zip
        Primary phone		primary_phone    +prefix
        Mobile phone		mobile_phone     +prefix
            Job info
        Affiliate		[affiliate_name]
        Position		[position]
        */
	}
	
	/**
	 * Get available titles.
	 *
	 * Get a list of available titles that the users can have.
	 *
	 * @Get("/titles")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("offset", type="integer", description="The page of results to view.", default=1),
	 *      @Parameter("limit", type="integer", description="The amount of results per page.", default=10)
	 * })
	 * @Response(200, body={
	 *    "errors": false,
	 *    "data": {
	 *        "Dr.",
	 *        "Mr.",
	 *        "Mrs.",
	 *        "Ms."
	 *    }
	 * })
	 *
	 * @return JSON
	 */
	public function getTitles(Request $request)
    {
        $titles = DB::table('dictionaries')
            ->where('group', 'user_title')
            ->orderBy('order_id', 'asc')
			->offset($this->offset)
			->limit($this->limit)
            ->pluck('value');
         
        return response()->success($titles);
    }

    /**
     * Returns a normalized state name, which can be used as the key for
     * `group` state_COUNTRY in the `dictionary` table.
     *
     * @param string $name Name to be normalized
     * @return string
     */
    private function normalizeCountry($name) {
        $lowerCaseName = strtolower($name);
        
        if ($lowerCaseName == 'united states') {
            return 'USA';
        }
        
        return $name;
    }

	/**
	 * Get available states.
	 *
	 * Get a list of values for the ‘state’ field that can be used in the current
	 * context.
	 *
	 * @Get("/me/states")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("offset", type="integer", description="The page of results to view.", default=1),
	 *      @Parameter("limit", type="integer", description="The amount of results per page.", default=100)
	 * })
	 * @Response(200, body={
	 *    "errors": false,
	 *    "data": {
	 *        "Alabama",
	 *        "Alaska",
	 *        "Arizona",
	 *        "Arkansas",
	 *        "California",
	 *        "Colorado",
	 *        "Connecticut",
	 *        "Delaware",
	 *        "District of Columbia",
	 *        "Florida"
	 *    }
	 * })
	 *
	 * @return JSON
	 */
	public function getMyStates(Request $request)
    {
        $user = Auth::user();
        
		$userCountryRow = DB::table('affiliates_addresses')
		    ->where(['affiliate_id' => $user->affiliate_id, 'type' => 'mailing'])
		    ->select('country')->first();
		
		if (!$userCountryRow) {
                return response()->error(TextHelper::t('The user is assigned to an affiliate without a mailing address, so we don’t know the user’s country and therefore don’t know the available states and phone formats'));
		}
		$limit = $request->get('limit', 100);
		$country = $this->normalizeCountry($userCountryRow->country);
        $states = DB::table('dictionaries')
                    ->where('group', 'state_' . strtolower($country))
                    ->orderBy('order_id', 'asc')
                    ->offset($this->offset)
                    ->limit($limit)
                    ->pluck('value');
         
        return response()->success($states);
    }

	/**
	 * Get available formats.
	 *
	 * Get a list of formats for some fields (currently phone fields).
	 *
	 * Phone fields have two formats: the original ‘phone’ format, where
	 * the alphabet characters are the placeholders for the digits and other
	 * characters are to be copied as-is, and the ‘regexp’ format which is used
	 * for validation.
	 *
	 * @Get("/me/formats")
	 * @Versions({"v1"})
	 * @Parameters({})
	 * @Response(200, body={
	 *    "errors": false,
	 *    "data": {
	 *        "phone": {
	 *            "primary_phone": "1-xxx-yyy-yyyy",
	 *            "mobile_phone": "1-xxx-yyy-yyyy"
	 *        },
	 *        "regexp": {
	 *            "primary_phone": "1\\-\\d\\d\\d\\-\\d\\d\\d\\-\\d\\d\\d\\d",
	 *            "mobile_phone": "1\\-\\d\\d\\d\\-\\d\\d\\d\\-\\d\\d\\d\\d"
	 *        }
	 *    }
	 * })
	 *
	 * @return JSON
	 */
	public function getMyFormats(Request $request)
    {
        $user = Auth::user();
        
		$userCountryRow = DB::table('affiliates_addresses')
		    ->where(['affiliate_id' => $user->affiliate_id, 'type' => 'mailing'])
		    ->select('country')->first();
		
		if (!$userCountryRow) {
                return response()->error(TextHelper::t('The user is assigned to an affiliate without a mailing address, so we don’t know the user’s country and therefore don’t know the available states and phone formats'));
		}
		$country = $this->normalizeCountry($userCountryRow->country);
		
        $countryData = DB::table('dictionaries')
            ->where(['group' => 'country',
                    'value' => $country])->first();
        if (!$countryData) {
                return response()->error(TextHelper::t('Invalid contry data set for the country ‘{$country}’'), ['country' => $country]);
        }
        
        $format = $countryData->data;
		
        $formats = [
            'phone' => [
                'primary_phone' => $format,
                'mobile_phone' => $format
            ],
            'regexp' => [
                'primary_phone' => self::phoneFormatToRegexp($format),
                'mobile_phone' => self::phoneFormatToRegexp($format)
            ]
        ];
         
        return response()->success($formats);
    }
    
	/**
	 * Change current user’s password
	 *
	 * Changes the password of the current user. The password should contain at
	 * least 1 character, and can only contain basic Latin letters and digits.
	 *
	 * @Post("/me/password")
	 * @Versions({"v1"})
	 * @Request({
	 *   "new_password": "abcd123"
	 * })
	 * @Transaction({
	 *      @Response(200, body={"errors": false, "data": "success"}),
	 *      @Response(422, body={
	 *        "message":"The new password format is invalid.",
	 *        "errors": {
	 *          {"new_password": "The new password format is invalid."}
	 *        }
	 *      })
	 * })
	 *
	 * @return JSON affiliate_id
	 */
	public function postPassword(Request $request)
	{
	    $user = Auth::user();

		$this->validate($request, [
			'new_password' => 'required|min:1|regex:/^[a-zA-Z0-9]+$/',
		]);

		$user->password = Hash::make($request->new_password);
		$user->save();
		
		return response()->success(TextHelper::t('success'));
	}

	/**
	 * Update user current context.
	 *
	 * TODO: not used? Maybe it can be removed?
	 *
	 * @return JSON success message
	 */
	public function putMe(Request $request)
	{
		$user = Auth::user();

		$this->validate($request, [
			'data.name' => 'required|min:3',
			'data.email' => 'required|email|unique:users,email,' . $user->id,
		]);

		$userForm = app('request')
			->only(
				'data.current_password',
				'data.new_password',
				'data.new_password_confirmation',
				'data.name',
				'data.email'
			);

		$userForm = $userForm['data'];
		$user->name = $userForm['name'];
		$user->email = $userForm['email'];

		if ($request->has('data.current_password')) {
			Validator::extend('hashmatch', function ($attribute, $value, $parameters) {
				return Hash::check($value, Auth::user()->password);
			});

			$rules = [
				'data.current_password' => 'required|hashmatch:data.current_password',
				'data.new_password' => 'required|min:8|confirmed',
				'data.new_password_confirmation' => 'required|min:8',
			];

			$payload = app('request')->only('data.current_password', 'data.new_password', 'data.new_password_confirmation');

			$messages = [
				'hashmatch' => TextHelper::t('Invalid Password'),
			];

			$validator = app('validator')->make($payload, $rules, $messages);

			if ($validator->fails()) {
				return response()->error($validator->errors());
			} else {
				$user->password = Hash::make($userForm['new_password']);
			}
		}

		$user->save();

		return response()->success(TextHelper::t('success'));
	}

	private function postprocessUsersQuery($query)
	{
	    $sortColumn = $this->sort;
	    if ($sortColumn == 'full_name' || $sortColumn == 'users.full_name' ) {
	        $sortColumn = DB::raw('CONCAT(first_name, " ", last_name)');
	    }
	    elseif (!strpos($sortColumn, '.')) {
	        $sortColumn = 'users.' . $this->sort;
	    }
	    
		$query->select(
				'users.*',
				DB::raw('CONCAT(first_name, " ", last_name) as full_name')
			)
			->leftJoin('position_user as pu', 'pu.user_id', '=', 'users.id')
			->leftJoin('positions as position', 'position.id', '=', 'pu.position_id')
			->offset($this->offset)
			->limit($this->limit)
			->orderBy($sortColumn, $this->direction);
		
		return $query;
	}

	/**
	 * Get a list of users.
	 *
	 * Get a list of users (filtering by groups if the current user is assigned
	 * to some group).
	 *
	 * @Resource("Users", uri="/users")
	 * @Get("/")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("sort", type="string", description="Name column for sorting <br/>[id,<br/>address1,<br/>address2,<br/>birthday,<br/>city,<br/>email,<br/>first_name,<br/>full_name,<br/>last_name,<br/>mobile_phone,<br/>position,<br/>primary_phone,<br/>state,<br/>title,<br/>username,<br/>zip]", default="id"),
	 *      @Parameter("direction", type="string", description="Name column for sorting [desc,asc]", default="asc"),
	 *      @Parameter("offset", type="integer", description="The page of results to view.", default=1),
	 *      @Parameter("limit", type="integer", description="The amount of results per page.", default=10),
	 *      @Parameter("respectGroup", type="int", description="Show only users from current user’s groups (1 show only users from the current user’s group, 0 shows all users)", default=1),
	 * })
	 *
	 * @return JSON
	 */
	public function index(Request $request)
	{
		$user = Auth::user();
		$position = $request->input('position');
		
		if($position) {
			$prepareQuery = User::whereHas('positions', function($query) use ($position) {
				$query->where('position_id', $position);
			});
		} else {
			$prepareQuery = User::with('positions');
		}
		
		if ($this->respectGroup && $user->isGroupMember()) {
		    $groupIds = GroupUser::where('user_id',$user->id)
		        ->pluck('group_id')->toArray();
		    $prepareQuery->leftJoin('group_user', function($q) use($groupIds){
                $q->on('group_user.user_id', '=', 'users.id');
                $q->whereIn('group_user.group_id', $groupIds);
            });
            $prepareQuery->whereIn('group_user.group_id', $groupIds);
		}
		else {
		    //groups can have users from sub-affiliates, so when we’re checking
		    //groups, we don’t need to check the affiliate_id
		    $prepareQuery->where('users.affiliate_id', '=', $user->affiliate_id);
		}

		$this->postprocessUsersQuery($prepareQuery);

		if($this->search != '') {
			$words = array_map('trim', explode(" ", $this->search));

			foreach($words as $word) {
				$prepareQuery = $prepareQuery
					->having('full_name', 'like', '%' . $word . '%');
			}
		}
		
		$countQuery = clone $prepareQuery;
		
		if ($this->respectGroup && $user->isGroupMember()) {
		    $prepareQuery->groupBy('users.id');
		}

		$data = $prepareQuery->get();
		$count = $countQuery->count(DB::raw('DISTINCT users.id'));

		return response()->success($data, $count);
	}

	/**
	 * Get user details referenced by id.
	 *
	 * @param int User ID
	 *
	 * @return JSON
	 */
	public function getShow($id)
	{
		$user = User::find($id);
		$user['role'] = $user
			->roles()
			->select(['slug', 'roles.id', 'roles.name'])
			->get();

		return response()->success($user);
	}

	/**
	 * Update user data.
	 *
	 * @return JSON success message
	 */
	public function putShow(Request $request)
	{
		$userForm = array_dot(
			app('request')->only(
				'data.name',
				'data.email',
				'data.id'
			)
		);

		$userId = intval($userForm['data.id']);

		$user = User::find($userId);

		$this->validate($request, [
			'data.id' => 'required|integer',
			'data.name' => 'required|min:3',
			'data.email' => 'required|email|unique:users,email,' . $user->id,
		]);

		$userData = [
			'name' => $userForm['data.name'],
			'email' => $userForm['data.email'],
		];

		$affectedRows = User::where('id', '=', $userId)->update($userData);

		$user->detachAllRoles();

		foreach (Input::get('data.role') as $setRole) {
			$user->attachRole($setRole);
		}

		return response()->success(TextHelper::t('success'));
	}

	/**
	 * Delete User Data.
	 *
	 * @return JSON success message
	 */
	public function deleteUser($id)
	{
		// $user = User::find($id);
		// $user->delete();
		return response()->success(TextHelper::t('success'));
	}

	/**
	 * Get all user roles.
	 *
	 * @return JSON
	 */
	public function getRoles()
	{
		$roles = Role::all();

		return response()->success(compact('roles'));
	}

	/**
	 * Get role details referenced by id.
	 *
	 * @param int Role ID
	 *
	 * @return JSON
	 */
	public function getRolesShow($id)
	{
		$role = Role::find($id);

		$role['permissions'] = $role
			->permissions()
			->select(['permissions.name', 'permissions.id'])
			->get();

		return response()->success($role);
	}

	/**
	 * Update role data and assign permission.
	 *
	 * @return JSON success message
	 */
	public function putRolesShow()
	{
		$roleForm = Input::get('data');
		$roleData = [
			'name' => $roleForm['name'],
			'slug' => $roleForm['slug'],
			'description' => $roleForm['description'],
		];

		$roleForm['slug'] = str_slug($roleForm['slug'], '.');
		$affectedRows = Role::where('id', '=', intval($roleForm['id']))->update($roleData);
		$role = Role::find($roleForm['id']);

		$role->detachAllPermissions();

		foreach (Input::get('data.permissions') as $setPermission) {
			$role->attachPermission($setPermission);
		}

		return response()->success(TextHelper::t('success'));
	}

	/**
	 * Create new user role.
	 *
	 * @return JSON
	 */
	public function postRoles()
	{
		$role = Role::create([
			'name' => Input::get('role'),
			'slug' => str_slug(Input::get('slug'), '.'),
			'description' => Input::get('description'),
		]);

		return response()->success(compact('role'));
	}

	/**
	 * Delete user role referenced by id.
	 *
	 * @param int Role ID
	 *
	 * @return JSON
	 */
	public function deleteRoles($id)
	{
		Role::destroy($id);

		return response()->success(TextHelper::t('success'));
	}

	/**
	 * Get all system permissions.
	 *
	 * @return JSON
	 */
	public function getPermissions()
	{
		$permissions = Permission::all();

		return response()->success(compact('permissions'));
	}

	/**
	 * Create new system permission.
	 *
	 * @return JSON
	 */
	public function postPermissions()
	{
		$permission = Permission::create([
			'name' => Input::get('name'),
			'slug' => str_slug(Input::get('slug'), '.'),
			'description' => Input::get('description'),
		]);

		return response()->success(compact('permission'));
	}

	/**
	 * Get system permission referenced by id.
	 *
	 * @param int Permission ID
	 *
	 * @return JSON
	 */
	public function getPermissionsShow($id)
	{
		$permission = Permission::find($id);

		return response()->success($permission);
	}

	/**
	 * Update system permission.
	 *
	 * @return JSON
	 */
	public function putPermissionsShow()
	{
		$permissionForm = Input::get('data');
		$permissionForm['slug'] = str_slug($permissionForm['slug'], '.');
		$affectedRows = Permission::where('id', '=', intval($permissionForm['id']))->update($permissionForm);

		return response()->success($permissionForm);
	}

	/**
	 * Delete system permission referenced by id.
	 *
	 * @param int Permission ID
	 *
	 * @return JSON
	 */
	public function deletePermissions($id)
	{
		Permission::destroy($id);

		return response()->success(TextHelper::t('success'));
	}

    /**
     * Send contact to support
     *
     * Send contact to support
     *
     * @Post("/users/sendmailtosupport")
     * @Versions({"v1"})
     * @Request({"subject": "Test subject", "message": "Test message"})
     * @Response(200,body={"data": true})
     *
     * @return JSON
     */
    public function postSendMailToSupport(Request $request)
    {
        $this->validate($request, [
            'subject' => 'required|max:255',
            'message' => 'required|max:5000',
        ]);

        $user = Auth::user();

        if (!$sentMail = $user->sendContactToSupport($request['subject'], $request['message'])) {
            return response()->error(TextHelper::t('Contact was not sent to support'), 403);
        }
        return response()->success($sentMail, null, 201);
    }
}
