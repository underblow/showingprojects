<?php

namespace App;

use App\Scopes\PersonsAttributedToUserScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

use FullContact;
use Illuminate\Support\Facades\DB;
use PiplApi_SearchAPIRequest;
use PiplApi_SearchRequestConfiguration;
use Mockery\Exception;

/**
 * App\Person
 *
 * @property int $id
 * @property string $email
 * @property string $full_name
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Person whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Person whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Person whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Person whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Person whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property-read \App\PersonFullcontactData $fullcontactData
 * @property-read string|null $birthday
 * @property-read string|null $first_name
 * @property-read string|null $gender
 * @property-read mixed $geo_location_data
 * @property-read string|null $last_name
 * @property-read mixed $mobile_phone
 * @property-read mixed $profile_image_url
 * @property-read string|null $retirement_savings
 * @property-read mixed $summary_info
 * @property-read \App\PersonPiplData $piplData
 * @property-read array|null $facebook_profile
 * @property-read string|null $linked_in_bio
 * @property-read array|null $linkedin_profile
 * @property int|null $user_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Person whereUserId($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Address[] $addresses
 * @property-read string|null $full_name_info
 */
class Person extends Model
{
    protected $table = 'persons';

    CONST infoProviderList = [
        'fullcontactData',
        'piplData',
    ];

    protected $appends = [
        'summary_info',
        'first_name',
        'last_name',
        'gender',
        'birthday',
        'retirement_savings',
        'mobile_phone',
        'facebook_profile',
        'linkedin_profile',
        'geo_location_data',
        'profile_image_url',
    ];

    public static function boot()
    {
        parent::boot();

        static::deleted(function ($company) {
            foreach($company->addresses as $address) {
                $address->delete();
            }
        });

        if (Auth::user()) {
            static::addGlobalScope(new PersonsAttributedToUserScope());
        }
    }

    public function piplData()
    {
        return $this->hasOne('App\PersonPiplData');
    }

    public function fullcontactData()
    {
        return $this->hasOne('App\PersonFullcontactData');
    }

    public function addresses()
    {
        return $this->morphMany('App\PersonAddress', 'addressable');
    }

    /**
     * @param string $nameField
     *
     * @return string|null
     */
    protected function getAttributeFromInfoProviders($nameField) {
        $valueToReturn = null;
        foreach (self::infoProviderList as $infoProvider) {
            if (!empty($valueToReturn)) {
                break;
            }
            if (isset($this->{$infoProvider}) && !empty($this->{$infoProvider}->{$nameField})) {
                $valueToReturn = $this->{$infoProvider}->{$nameField};
            }
        }

        return $valueToReturn;
    }

    /**
     * @return string|null
     */
    public function getProfileImageUrlAttribute() {
        return $this->getAttributeFromInfoProviders('profileImage');
    }

    public function getMobilePhoneAttribute() {
        return $this->getAttributeFromInfoProviders('number');
    }

    /**
     * @return array|null
     */
    public function getFacebookProfileAttribute() {
        return !empty($this->fullcontactData) ? $this->fullcontactData->facebookProfile : null;
    }

    /**
     * @return array|null
     */
    public function getLinkedinProfileAttribute() {
        return !empty($this->fullcontactData) ? $this->fullcontactData->linkedInProfile : null;
    }

    /**
     * @return null|string
     */
    public function getSummaryInfoAttribute() {
        return $this->getLinkedInBioAttribute();
    }

    /**
     * @return string|null
     */
    public function getLinkedInBioAttribute() {
        return !empty($this->fullcontactData) ? $this->fullcontactData->linkedInBio : null;
    }

    /**
     * @return string|null
     */
    public function getFirstNameAttribute() {
        return $this->getAttributeFromInfoProviders('first_name');
    }

    /**
     * @return string|null
     */
    public function getLastNameAttribute() {
        return $this->getAttributeFromInfoProviders('last_name');
    }

    /**
     * @return string|null
     */
    public function getFullNameInfoAttribute() {
        return $this->getFirstNameAttribute() . ' ' . $this->getLastNameAttribute();
    }

    /**
     * @return string|null
     */
    public function getGenderAttribute() {
        return $this->getAttributeFromInfoProviders('gender');
    }

    /**
     * @return string|null
     */
    public function getBirthdayAttribute() {
        return $this->getAttributeFromInfoProviders('birthday');
    }

    /**
     * @return string|null
     */
    public function getRetirementSavingsAttribute() {
        return $this->getAttributeFromInfoProviders('retirement_savings');
    }

    /**
     * @return null|string
     */
    public function getGeoLocationDataAttribute() {
        return $this->getAttributeFromInfoProviders('geo_location');
    }

    /**
     * @return bool
     */
    public function fillAdditionalDataFromInfoProviders() {
        try {
            DB::beginTransaction();

            $configuration = new PiplApi_SearchRequestConfiguration();
            $configuration->api_key = env('PIPL_APIKEY');

            $request = new PiplApi_SearchAPIRequest([
                'email' => $this->email, // 'clark.kent@example.com',
            ], $configuration);

            $personPiplData = PersonPiplData::wherePersonId($this->id)->first();
            if (!$personPiplData) {
                $personPiplData = new PersonPiplData();
                $personPiplData->person_id = $this->id;
            }

            try {
                $response = $request->send();

                $personPiplData->first_name = $response->name()->first;
                $personPiplData->middle_name = $response->name()->middle;
                $personPiplData->last_name = $response->name()->last;

                $personPiplData->country = $response->address()->country;
                $personPiplData->state = $response->address()->state;
                $personPiplData->city = $response->address()->city;
                $personPiplData->street = $response->address()->street;
                $personPiplData->house = $response->address()->house;
                $personPiplData->apartment = $response->address()->apartment;
                $personPiplData->zip_code = $response->address()->zip_code;

                $personPiplData->country_code = $response->phone()->country_code;
                $personPiplData->number = $response->phone()->number;

                $personPiplData->gender = $response->gender()->content;

                $personPiplData->language = $response->language()->language;
                $personPiplData->region = $response->language()->region;

                $personPiplData->response_data = json_encode(json_decode($response->raw_json));
            } catch (\Exception $e) {
                $personPiplData->response_code = 1;
                $personPiplData->response_data = $e->getMessage();
            }
            $personPiplData->save();

            // save Fullcontact data
            $personFullcontactData = PersonFullcontactData::wherePersonId($this->id)->first();
            if (!$personFullcontactData) {
                $personFullcontactData = new PersonFullcontactData();
                $personFullcontactData->person_id = $this->id;
            }

            try {
                $response = FullContact::lookupByEmail($this->email); //  'bart@fullcontact.com'
                $personFullcontactData->first_name = $response->contactInfo->familyName ?? null;
                $personFullcontactData->middle_name = $response->contactInfo->middleName ?? null;
                $personFullcontactData->last_name = $response->contactInfo->givenName ?? null;

                $personFullcontactData->country = $response->demographics->locationDeduced->country->code ?? null;
                $personFullcontactData->state = $response->demographics->locationDeduced->state->code ?? null;
                $personFullcontactData->city = $response->demographics->locationDeduced->city->name ?? null;

                $personFullcontactData->gender = $response->demographics->gender ?? null;
                $personFullcontactData->response_data = json_encode($response);
            } catch (Exception $e) {
                $personFullcontactData->response_code = 1;
                $personFullcontactData->response_data = $e->getMessage();
            }
            $personFullcontactData->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }

        return true;
    }
}
