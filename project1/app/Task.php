<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use DB;

class Task extends Model
{
    const STATUS_OPEN = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_OVERDUE = 2;

    protected $dates = [
      'created_at',
      'updated_at',
      'due_date',
      'completed_at'
    ];

    protected $appends = ['created_date', 'updated_date', 'completed_date', 'is_delegated'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'user_id','assignee_id', 'assignor_id', 'updated_at','created_at', 'completed_at', 'assigned_patient_id'
    ];

    public function case()
    {
        return $this->belongsTo('App\CaseM');
    }

    public function user()
    {
        return $this->belongsTo('App\User')->select('id','first_name','last_name','title');
    }

    public function modified_by()
    {
        return $this->belongsTo('App\User', 'modified_by', 'id')->select('id','first_name','last_name','title');
    }

    public function created_user()
    {
        return $this->user();
    }

    public function modified_user()
    {
        return $this->modified_by();
    }

    public function assignee()
    {
        return $this->belongsTo('App\User')->select('id','first_name','last_name','title');
    }

    public function assigned_user()
    {
      return $this->assignee();
    }
    
    public function assigned_patient()
    {
        return $this->belongsTo('App\Patient')->select('id', 'id_code', 'first_name', 'last_name', 'user_id');
    }
    
    public function assignor()
    {
        return $this->belongsTo('App\User')->select('id','first_name','last_name','title');
    }
    
    public function assignor_user()
    {
        return $this->assignor();
    }

    public function getCreatedDateAttribute()
    {
        return isset($this->attributes['created_at']) ? Carbon::parse($this->attributes['created_at'])->timestamp : null;
    }

    public function getUpdatedDateAttribute()
    {
        return isset($this->attributes['updated_at']) ? Carbon::parse($this->attributes['updated_at'])->timestamp : null;
    }
    
    public function getCompletedDateAttribute()
    {
        return isset($this->attributes['completed_at']) ? Carbon::parse($this->attributes['completed_at'])->timestamp : null;
    }

    public function scopeCompleted($query)
    {
        return $query->where('tasks.status', 1);
    }

    function getDueDateAttribute()
    {
        return Carbon::parse($this->attributes['due_date'])->timestamp;
    }

    function scopeAllRelations($query){
        return $query->with(['case.patient','assigned_user','created_user', 'modified_user', 'assignor_user']);
    }

    function scopeTasksOrderBy($query,$order,$direction){
        $prepareQuery = $query;

        if($order === 'created' || $order === 'assigned'){
            $prepareQuery->leftJoin('users','users.id','=',$this->sort === 'created' ? 'tasks.user_id' : 'tasks.assignee_id' )
            ->orderBy('first_name', $direction);
        }else if($order === 'case.id_code'){
            $prepareQuery->leftJoin('cases','cases.id','=','tasks.case_id' )
            ->orderBy('cases.id_code', $direction);
        }else if($order === 'patient.first_name'){
            $prepareQuery->leftJoin('cases','cases.id','=','tasks.case_id' )
            ->leftJoin('patients','patients.id','=','cases.patient_id' )
            ->orderBy('patients.first_name', $direction);
        }else{
            $prepareQuery->orderBy($order, $direction);
        }

        return $prepareQuery;
    }

    function scopeAdminAccess($query,$user_id,$filter_groupid = null,$filter_groupuserid = null){
      $query->join('group_user as gua', function($join) use($user_id, $filter_groupid)
      {
          $join->on('gua.is_admin', '=', DB::raw(1));
          $join->on('gua.user_id', '=', DB::raw($user_id));

          if(!empty($filter_groupid)){
              $join->on('gua.group_id', '=', DB::raw($filter_groupid));
          }
      })->join('group_user as gum', function($join) use($user_id, $filter_groupuserid){
          $join->on('gum.group_id', '=', 'gua.group_id');
      });

      if(!empty($filter_groupuserid)){
          $query->whereIn('gum.user_id', explode(",",DB::raw($filter_groupuserid)));
      }

      $query->where(function ($q) use ($user_id) {
          $q->where('tasks.user_id','=', DB::raw('gum.user_id'))
            ->orWhere('tasks.assignee_id','=', DB::raw('gum.user_id'));
      });

      return $query;
    }

    public function survey()
    {
        return $this->belongsTo('App\Survey');
    }

    public function answers()
    {
        return $this->hasMany('App\TaskAnswer');
    }

    public function step()
    {
        return $this->belongsTo('App\Step');
    }

    public function scheduleCaseTreatmentPath()
    {
        return $this->belongsTo('App\ScheduleCaseTreatmentPath', 'schedule_case_treatment_path_id');
    }

    /**
     * @param Task $task
     * @param bool $firstIteration
     */
    public static function modifyDependedTaskDueDates(Task $task, $firstIteration = false, $dependency_type = ScheduleTreatmentPath::DEPENDENCY_TYPE_START_END) {
      /** @var ScheduleCaseTreatmentPath $scheduleCaseTreatmentPath */
        $scheduleCaseTreatmentPath = $task->scheduleCaseTreatmentPath;
        if (empty($scheduleCaseTreatmentPath)) {
            return;
        }
        if ($firstIteration) {
            $scheduleCaseTreatmentPath->depend_from_schedule_case_treatment_path_id = 0;
            $scheduleCaseTreatmentPath->dependency_type = $dependency_type;
            $scheduleCaseTreatmentPath->save();
        }

        /** @var ScheduleCaseTreatmentPath[] $childScheduleCases */
        $childScheduleCases = $scheduleCaseTreatmentPath->childSchedules;
        foreach ($childScheduleCases as $childScheduleCase) {
            $connectedChildTask = $childScheduleCase->task;
            if (!empty($connectedChildTask)) {
                $parentTaskDueDate = Carbon::createFromTimestamp($task->due_date);

                switch ($childScheduleCase->dependency_type) {
                    case ScheduleTreatmentPath::DEPENDENCY_TYPE_START_START:
                        $dueDate = Carbon::parse($parentTaskDueDate)->subDay($scheduleCaseTreatmentPath->duration_day)->addDay($childScheduleCase->duration_day);
                        break;
                    case ScheduleTreatmentPath::DEPENDENCY_TYPE_START_END:
                        $dueDate = Carbon::parse($parentTaskDueDate)->addDay($childScheduleCase->duration_day);
                        break;
                }
                $connectedChildTask->due_date = $dueDate;

                $connectedChildTask->save();
                self::modifyDependedTaskDueDates($connectedChildTask);
            }
        }
    }

    public function getIsDelegatedAttribute() {
        return (($this->assignee !== null) && ($this->assignee->id != $this->user->id));
    }
}


