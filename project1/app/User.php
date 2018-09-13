<?php

// use Illuminate\Foundation\Auth\User as Authenticatable;

namespace App;

use App\Services\NetHelper;
use Ultraware\Roles\Contracts\HasRoleAndPermission as HasRoleAndPermissionContract;
use Ultraware\Roles\Traits\HasRoleAndPermission;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use DB;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
	use Authenticatable, CanResetPassword, HasRoleAndPermission;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'name', 'email', 'password', 'avatar',
	];

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = [
		'password', 'remember_token', 'oauth_provider_id', 'oauth_provider'
	];

	public function affiliate()
	{
		return $this->hasOne('App\Affiliate', 'id', 'affiliate_id')->select(['id','name','image','abbreviation']);
	}

	public function positions()
	{
		return $this->belongsToMany('App\Position', 'position_user', 'user_id', 'position_id')->select(['positions.name']);
	}

	public function roleUser() {
		return $this->hasOne('App\RoleUser');
	}

	public function roles() {
		return $this->belongsToMany('App\Role');
	}

	public function groups()
	{
		return $this->belongsToMany('App\Group', 'group_user')->select(['groups.id','groups.name','group_user.is_admin']);
	}

	public function isGroupAdmin()
	{
		return GroupUser::where('user_id',$this->id)->where('is_admin',1)->exists();
	}

	public function isGroupMember()
	{
		return GroupUser::where('user_id',$this->id)->exists();
	}

	public function isHasClinicalPath($id)
	{
		return UserClinicalPath::where('user_id',$this->id)->where('clinical_path_id',$id)->exists();
	}

	public function isUserInMyAdminGroups($userId)
	{
		return GroupUser::where('group_user.user_id',$this->id)
			->where('group_user.is_admin',1)
			->join('group_user as gum', function($join) use($userId)
		{
			$join->on('gum.group_id', '=', 'group_user.group_id');
			$join->on('gum.user_id', '=', DB::raw($userId));
		})->exists();
	}

	/**
	 * Chech the user's role
	 *
	 * e.g. 
	 * if($app->user->roleIs('admin')) {
	 * 	// do stuff
	 * } else {
	 *  // 401
	 * }
	 */
	public function roleIs($slug) {
		$roles = $this->roles()->get();

		foreach($roles as $role) {
			if($role->slug == $slug) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the user has a certain permission
	 *
	 * e.g. 
	 * if($app->user->can('my.profile', 'edit')) {
	 * 	// do stuff
	 * } else {
	 * 	// 401
	 * }
	 */
	public function can($permission_name, $action) {
		$roles = $this->roles()->get();

		foreach($roles as $role) {
			if(($permission = $role->getPermission($permission_name)) != null) {
				if($permission[$action] === 1) {
					return true;
				} 
			}
		}

		return false;
	}

	public function toArray()
	{
		$attributes = $this->attributesToArray();
		$attributes = array_merge($attributes, $this->relationsToArray());

		if(isset($attributes['image']) && $attributes['image']){
		    $path = env('ADMIN_URL')."/uploads/user/".$attributes['image'];
        $attributes['image'] = NetHelper::remoteFileExists($path) ? $path : '';
		}

		if(isset($attributes['logo']) && $attributes['logo']){
        $path = env('ADMIN_URL')."/uploads/user/".$attributes['logo'];
        $attributes['logo'] = NetHelper::remoteFileExists($path) ? $path : '';
		}

		if(!empty($attributes['birthday'])) {
		    $birthday = Carbon::createFromFormat('m/d/Y', $attributes['birthday'])->startOfDay();
		    $attributes['birthday_timestamp'] = $birthday->timestamp;
		}

		if (array_key_exists('image',$attributes) && is_null($attributes['image'])) {
        $attributes['image'] = "";
    }

    if (array_key_exists('logo',$attributes) && is_null($attributes['logo'])) {
        $attributes['logo'] = "";
    }

		return $attributes;
	}

    /**
     * @return array
     */
    public function getPublicContacts() {
        $return = [];
        
        if ($this->is_public_basic) {
            $return['title'] = $this->title;
            $return['first_name'] = $this->first_name;
            $return['last_name'] = $this->last_name;
            $return['email'] = $this->email;
        }
        
        if ($this->is_public_contact) {
            $return['birthday'] = $this->birthday;
            $return['address1'] = $this->address1;
            $return['address2'] = $this->address2;
            $return['city'] = $this->city;
            $return['state'] = $this->state;
            $return['zip'] = $this->zip;
            $return['primary_phone'] = $this->primary_phone;
            $return['mobile_phone'] = $this->mobile_phone;
        }
        
        if ($this->is_public_job) {
            $return['affilate'] = $this->affiliate;
            $return['position'] = (count($this->positions) ? $this->positions[0] : null);
        }

        return $return;
    }

    /**
     * @param string $subject
     * @param string $message
     * @param string $supportEmail
     *
     * @return bool
     */
    public function sendContactToSupport($subject, $message, $supportEmail = false) {
        if (!$supportEmail) {
            $supportEmail = config('mail.support_email');
        }
        
        $user = $this;
        \Mail::send('emails.sent_contact_to_support', [
            'user'      => $user,
            'subject'   => $subject,
            'emailText' => $message,
        ], function ($m) use ($user, $supportEmail) {
            $m->from('no-reply@test.com', 'Project');

            $m->to($supportEmail, 'Support')
                ->subject('New message for Support');
        });

        # remove 1 when begin to test in the server
        return 1 || count(\Mail::failures()) == 0;
    }
}
