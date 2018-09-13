<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\DeletedScope;
use Carbon\Carbon;

class Patient extends Model
{
	protected static function boot()
	{
		parent::boot();

		static::addGlobalScope(new DeletedScope);
	}

	protected $attributes = array(
		'is_active' => 1,
	);

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [

	];

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = [
		'is_deleted'
	];

	public function cases()
	{
		return $this->hasMany('App\CaseM');
	}

	public function countCases()
	{
		return $this->hasOne('App\CaseM')
			->selectRaw('patient_id, count(*) as count')
			->groupBy('patient_id')
			->where('is_deleted', '<>', '1');
	}

	public function getCountCasesAttribute()
	{
		// if relation is not loaded already, let's do it first
		if (!array_key_exists('countCases', $this->relations))
			$this->load('countCases');

		$related = $this->getRelation('countCases');

		// then return the count directly
		return ($related) ? (int)$related->count : 0;
	}

	public function getTableColumns()
	{
		return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
	}
	
	public function toArray()
	{
		$attributes = $this->attributesToArray();
		$attributes = array_merge($attributes, $this->relationsToArray());

		if(isset($attributes['birthdate']) && !empty($attributes['birthdate'])){
			$attributes['birthdate'] = Carbon::parse($attributes['birthdate'])->timestamp;
		}

		return $attributes;
	}
}
