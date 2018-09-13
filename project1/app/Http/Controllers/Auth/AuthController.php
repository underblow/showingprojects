<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TextHelper;
use App\User;
use App\EmailTemplate;
use Auth;
use JWTAuth;
use JWTFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * @Resource("Authenticate", uri="/v1/auth")
 *
 * @resource Authenticate
 */
class AuthController extends Controller
{
	/**
	 * Authenticate user.
	 *
	 * @Post("/login")
	 * @Request({"username": "foo", "password": "bar", "device": "21E7FABA-B337-42C7-AA72-06A0E645DEA7"})
	 * @Responce({"token": "foo"})
	 *
	 * @return JSON
	 */
	public function postLogin(Request $request)
	{
		$this->validate($request, [
			'username' => 'required',
			'password' => 'required',
			'device' => 'required'
		]);

		$credentials = $request->only('username', 'password');

		try {
			if (!$token = JWTAuth::attempt($credentials, ['app' => '', 'device' => $request->device])) {
				return response()->error(TextHelper::t('Invalid credentials'), 403);
			}
		} catch (\JWTException $e) {
			return response()->error(TextHelper::t('Could not create token'), 500);
		}

		$user = Auth::user();

		if (!$user->is_active) {
			return response()->error(TextHelper::t('Your account deactivated'), 401);
		}

		$token = JWTAuth::fromUser($user);

		// check if other device
		if ($old_token = DB::table('tokens')->where('user_id', $user->id)->where('logout_reason', 0)->first()) {
			try {
				JWTAuth::setToken($old_token->refresh_token);
				$set_old_token = JWTAuth::getToken($old_token->refresh_token);
				JWTAuth::setRefreshFlow(true);
				$decode_token_parse = JWTAuth::decode($set_old_token);

				if ($decode_token_parse['device'] !== $request->device) {
					DB::table('tokens')->where('refresh_token', $old_token->refresh_token)->where('logout_reason', 0)->update(['logout_reason' => 1]);
				}
			} catch (JWTException $e) {
				DB::table('tokens')->where('user_id', $user->id)->where('logout_reason', '=', 0)->delete();
			}
		}

		DB::table('tokens')->where('user_id', $user->id)->where('logout_reason', '<>', 1)->delete();

		DB::table('tokens')->insert(
			['token_id' => $token, 'user_id' => $user->id, 'refresh_token' => $token]
		);

		return response()->success(['token' => $token]);
	}

	/**
	 * Reset password.
	 *
	 * @Post("/password/reset")
	 * @Versions({"v1"})
	 * @Request({"username": "foo"})
	 * @Response(200)
	 *
	 * @return JSON
	 */
	public function reset(Request $request)
	{
		$this->validate($request, [
			'username' => 'required',
		]);

		$field = filter_var($request->json('username'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

		$this->validate($request, [
			'username' => 'required|exists:users,' . $field,
		], ['username.exists' => TextHelper::t('Cannot find user, please re-enter.')]);

		if ($field === 'email') {
			$user = User::whereEmail($request->username)->firstOrFail();
		} else {
			$user = User::whereUsername($request->username)->firstOrFail();
		}

		# Auto-generated password should be 8 characters length and contains letters as well as digits.
		# The password should be: YyXXXXyy, where 'Y' is a capital letter, selected randomly,
		# 'y' is a sequence of randomly generated sentence case letters, 'X' is a sequence of randomly generated digits

		$Y = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$y = "abcdefghijklmnopqrstuvwxyz";
		$XXXX = rand(1000, 9999);

		$new_password_template = ['Y' => '', 'y' => '', 'XXXX' => '', 'yy' => ''];

		$new_password_template['Y'] = substr(str_shuffle(str_repeat($Y, 1)), 0, 1);
		$new_password_template['y'] = substr(str_shuffle(str_repeat($y, 1)), 0, 1);
		$new_password_template['XXXX'] = $XXXX;
		$new_password_template['yy'] = substr(str_shuffle(str_repeat($y, 2)), 0, 2);

		$new_password = implode('', $new_password_template);

		//$new_password = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 8)), 0, 8);
		$user->password = bcrypt($new_password);
		$user->save();

		$email = $user->email;
		$firstname = $user->first_name;
		$lastname = $user->last_name;
		$username = $user->username;
		$prefix = $user->title;


		$template = EmailTemplate::getTemplate('restore_password',$user->affiliate_id);

		if($template){
			$varibles = array_fill_keys(array_column($template->toArray()['varibles'],'name'),'');
			$varibles['$firstname$'] = $firstname;
			$varibles['$prefix$'] = $prefix;
			$varibles['$lastname$'] = $lastname;
			$varibles['$username$'] = $username;
			$varibles['$new_password$'] = $new_password;

			$body = str_replace(array_keys($varibles), array_values($varibles), $template->body);
			$subject = str_replace(array_keys($varibles), array_values($varibles), $template->subject);

			Mail::send('emails.templates.reset_link', [
				'body' => $body
			], function ($mail) use ($email,$subject) {
				$mail->to($email)
					->subject($subject);
			});
		}else{
			Mail::send('emails.reset_link', compact('email', 'firstname', 'lastname', 'username', 'new_password', 'prefix'), function ($mail) use ($email) {
				$mail->to($email)
					->subject('Project Password Reset');
			});

		}

		return response()->success(true);
	}
}


