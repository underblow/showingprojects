<?php

namespace App\Services;

use DB;
use Carbon\Carbon;

/**
 * Gets arguments for the filters and uses them in the Eloquent queries.
 */
class FilterInputParser
{
	/**
	 * @var string[] Field names that are used in the $fields field.
	 */
	private static $fieldNames = [
		"from",
		"to",
		"duedate",
		"assignee",
		"assignor",
		"surveystatus",
		"casestatus",
		"patient",
		"cp",
		"tp",
		"tpstep",
		"caseid",
		"group",
		"usergroup"
	];

	/**
	 * @var array The fields that accept comma-separated lists of IDs.
	 */
	private static $commaSeparated = [
		"assignee",
		"assignor",
		"patient",
		"cp",
		"tp",
		"tpstep",
		"caseid"
	];

	/**
	 * @var \stdClass Object with fields that were received from the request.
	 * The fields that are received are set in the $fieldNames array.
	 */
	private $fields;

	/**
	 * Constructs a new instance, parsing the request arguments.
	 *
	 * @param \Dingo\Api\Http\Request Request that is used to get filter
	 * variables.
	 */
	public function __construct(\Dingo\Api\Http\Request $request)
	{
		$this->fields = new \stdClass;

		$filters = $request->get("filter", []);

		foreach (self::$fieldNames as $fieldName) {

			if (in_array($fieldName, self::$commaSeparated)) {
				if (isset($filters[$fieldName])) {
					$value = $filters[$fieldName];
					$values = array_map('trim', explode(',', $value));
					$this->fields->$fieldName = $values;
				} else {
					$this->fields->$fieldName = [];
				}
			} else {
				if (isset($filters[$fieldName])) {
					$value = $filters[$fieldName];
					$this->fields->$fieldName = $value;
				} else {
					$this->fields->$fieldName = false;
				}
			}
		}
	}

	/**
	 * Modifies the tasks query to include the filters.
	 * @param object Query to modify.
	 * @param string Name of the tasks table (either 'tasks' or an alias; can be
	 * empty if filtering by tasks is not needed)
	 * @param string Name of the cases table (either 'cases' or an alias; can be
	 * empty if filtering by cases is not needed)
	 * @param string Name of the treatment paths table (can be an alias; can be
	 * empty if filtering by TPs is not needed)
	 * @param array Array with additinal arguments
	 */
	private function modifyQuery($query, $tasksTable, $casesTable, $tpTable, $filteredTasksTable = false, $args)
	{
		$type = 'default';
		if (!empty($args['type'])) {
			$type = $args['type'];
		}

		$query->whereNested(function ($query) use ($tasksTable, $casesTable, $tpTable, $filteredTasksTable, $args) {
			if (!empty($args['addConditions'])) {
				$args['addConditions']($query);
			}

			if ($tasksTable) {
				if (count($this->fields->assignee)) {
					$query->whereIn("{$tasksTable}.assignee_id", $this->fields->assignee);
				}
				if (count($this->fields->assignor)) {
					$query->whereIn("{$tasksTable}.assignor_id", $this->fields->assignor);
				}
				if ($this->fields->surveystatus !== false) {
					$query->where("{$tasksTable}.status", '=', $this->fields->surveystatus);
				}
				if (!$casesTable) {
					if (count($this->fields->caseid)) {
						$query->whereIn("{$tasksTable}.case_id", $this->fields->caseid);
					}
				}
			}

			if ($casesTable) {
				if ($this->fields->casestatus !== false) {
					$query->where("{$casesTable}.status", '=', $this->fields->casestatus);
				}
				if (count($this->fields->patient)) {
					$query->whereIn("{$casesTable}.patient_id", $this->fields->patient);
				}
				if (count($this->fields->tp)) {
					$query->whereIn("{$casesTable}.treatment_path_id", $this->fields->tp);
				}
				if (count($this->fields->tpstep)) {
					$query->whereIn("{$casesTable}.step_id", $this->fields->tpstep);
				}

				if (count($this->fields->caseid)) {
					$query->whereIn("{$casesTable}.id", $this->fields->caseid);
				}
			}
			if ($tpTable) {
				if (count($this->fields->cp)) {
					$query->whereIn("{$tpTable}.clinical_path_id", $this->fields->cp);
				}
			}
			if ($filteredTasksTable) {
				$query->whereNotNull("{$filteredTasksTable}.id");
			}
		});

		return $query;
	}

	/**
	 * This is used for cases queries when the case has more than one task.
	 * Then, we do a join with our conditions, and check that the joined
	 * fields are not null.
	 *
	 * @param object Query to modify.
	 * @param string Name of the cases table (either 'cases' or an alias).
	 * @param string Alias for the tasks table
	 * @param array Array of additional arguments
	 * @return bool True if the join was added, false otherwise
	 */
	private function modifyQueryWithManyTasks($query, $casesTable, $tasksTable, $args)
	{
		$filterDueDate = false;
		if (!empty($args['filterDueDate'])) {
			$filterDueDate = boolval($args['filterDueDate']);
		}

		if (($filterDueDate && $this->fields->duedate !== false)
			|| count($this->fields->assignee)
		    || count($this->fields->assignor)
			|| $this->fields->surveystatus !== false
		) {
			$fields = $this->fields;

			// Check that the case has at least one matching task
			$query->leftJoin(
				"tasks AS {$tasksTable}",
				function ($join) use ($casesTable, $tasksTable, $fields, $filterDueDate) {
					$taskConditions = ["`tasks`.`case_id` = `{$casesTable}`.`id`"];

					if ($filterDueDate) {
						if ($fields->duedate) {
							$taskConditions[] = "DATE(`tasks`.`due_date`) = '" . Carbon::createFromTimestamp($fields->duedate)->toDateString() . "'";
						}
					}

					if (count($fields->assignee)) {
						$assigneeIds_s = implode(', ', array_map('intval', $fields->assignee));
						$taskConditions[] = "`tasks`.`assignee_id` IN ({$assigneeIds_s})";
					}
					if (count($fields->assignor)) {
						$assignorIds_s = implode(', ', array_map('intval', $fields->assignor));
						$taskConditions[] = "`tasks`.`assignor_id` IN ({$assignorIds_s})";
					}

					if ($fields->surveystatus !== false) {
						$taskConditions[] = "`tasks`.`status` = " . intval($fields->surveystatus);
					}
					$taskConditions_s = implode(" AND ", $taskConditions);

					$join->on("{$tasksTable}.id", '=',
						DB::raw("(
                                select `tasks`.`id` from `tasks`
                                where $taskConditions_s 
                                order by `tasks`.`id`
                                limit 1
                         )"));
				}
			);
			return true;
		}

		return false;
	}

	/**
	 * Modifies the tasks query to include the filters.
	 * @param object Query to modify.
	 */
	public function modifyTasksQuery($query)
	{
		if ($this->fields->casestatus !== false
			|| count($this->fields->patient)
			|| count($this->fields->caseid)
			|| count($this->fields->tp)
			|| count($this->fields->cp)
			|| count($this->fields->tpstep)
		) {
			$query->leftJoin('cases AS filtered_cases',
				'filtered_cases.id', '=', 'tasks.case_id');
			if (count($this->fields->cp)) {
				$query->leftJoin('treatment_paths AS filtered_treatment_paths',
					'filtered_treatment_paths.id', '=', 'filtered_cases.treatment_path_id');
			}
		}

		$fields = $this->fields;
		$this->modifyQuery($query, 'tasks', 'filtered_cases', 'filtered_treatment_paths', false, [
			'addConditions' => function ($query) use ($fields) {
				if ($fields->from !== false) {
					$query->whereDate("tasks.due_date", '>=', Carbon::createFromTimestamp($fields->from)->toDateString());
				}
				if ($fields->to !== false) {
					$query->whereDate("tasks.due_date", '<=', Carbon::createFromTimestamp($fields->to)->toDateTimeString());
				}

				if ($fields->duedate) {
					$query->whereDate("tasks.due_date", '=', Carbon::createFromTimestamp($fields->duedate)->toDateString());
				}
			}
		]);

		return $query;
	}

	/**
	 * Modifies the details query to take the filters into account.
	 * @param object Query to modify.
	 */
	public function modifyDetailsTasksQuery($query)
	{
		$fields = $this->fields;
		$addConditionsFn = function ($query) use ($fields) {
			if ($fields->from !== false) {
				//when due date is set, from/to filters on complete date
				$query->whereDate("cases.created_at", '>=', Carbon::createFromTimestamp($fields->from)->toDateString());
			}
			if ($fields->to !== false) {
				//when due date is set, from/to filters on complete date
				$query->whereDate("cases.created_at", '<=', Carbon::createFromTimestamp($fields->to)->toDateString());
			}

			if ($fields->duedate) {
				$query->whereDate("tasks.due_date", '=', Carbon::createFromTimestamp($fields->duedate)->toDateString());
			}
		};

		$this->modifyQuery($query, 'tasks', 'cases', 'treatment_paths', false, [
			'type' => 'details',
			'addConditions' => $addConditionsFn
		]);

		return $query;
	}

	/**
	 * Modifies the details cases query.
	 * @param object Query to modify.
	 */
	public function modifyDetailsCasesQuery($query)
	{
		$fields = $this->fields;
		$addConditionsFn = function ($query) use ($fields) {
			if ($fields->from !== false) {
				//when due date is set, from/to filters on complete date
				$query->whereDate("cases.created_at", '>=', Carbon::createFromTimestamp($fields->from)->toDateString());
			}
			if ($fields->to !== false) {
				//when due date is set, from/to filters on complete date
				$query->whereDate("cases.created_at", '<=', Carbon::createFromTimestamp($fields->to)->toDateString());
			}
		};

		if ($this->modifyQueryWithManyTasks($query, 'cases', 'filtered_tasks', ['filterDueDate' => true])) {
			$this->modifyQuery($query, false, 'cases', 'treatment_paths', 'filtered_tasks', [
				'type' => 'details',
				'addConditions' => $addConditionsFn
			]);
			$query->groupBy('cases.id');
		} else {
			$this->modifyQuery($query, false, 'cases', 'treatment_paths', false, [
				'type' => 'details',
				'addConditions' => $addConditionsFn
			]);
		}

		return $query;
	}

	/**
	 * @param object Query to modify.
	 */
	public function modifyCpCasesQuery($query)
	{
		$fields = $this->fields;
		$addConditionsFn = function ($query) use ($fields) {
			if ($fields->from !== false) {
				//when due date is set, from/to filters on complete date
				$query->whereDate("cases.created_at", '>=', Carbon::createFromTimestamp($fields->from)->toDateString());
			}
			if ($fields->to !== false) {
				//when due date is set, from/to filters on complete date
				$query->whereDate("cases.created_at", '<=', Carbon::createFromTimestamp($fields->to)->toDateString());
			}
		};

		if ($this->modifyQueryWithManyTasks($query, 'cases', 'filtered_tasks', ['filterDueDate' => true])) {
			$this->modifyQuery($query, false, 'cases', 'treatment_paths', 'filtered_tasks', [
				'type' => 'details',
				'addConditions' => $addConditionsFn
			]);
		} else {
			$this->modifyQuery($query, false, 'cases', 'treatment_paths', false, [
				'type' => 'details',
				'addConditions' => $addConditionsFn
			]);
		}

		return $query;
	}
}
