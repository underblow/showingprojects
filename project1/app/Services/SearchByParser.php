<?php

namespace App\Services;

class SearchByParser
{
	/**
	 * @var array Array of strings that contain possible fields that are allowed
	 * in the user input and are passed into SQL directly, such as
	 * patients.first_name.
	 */
	public $basicFields;
	
	/**
	 * @var array Associative array of string that are resolved into basic
	 * fields. The keys are field that is an allowed in the user input. The
	 * values are arrays of the basic field names (from $basicFields) that are
	 * used in the resulting SQL.
	 */
	public $combinedFields;
	
	/**
	 * @var string The default value that will be used if an empty string is
	 * passed.
	 */
	public $default;
	
	/**
	 * Converts the search_by string to an array of database fields,
	 * throwing an exception if impossible field is passed.
	 *
	 * The  $basicFields and $combinedFields
	 *
	 * @param string Comma-separated list of fields
	 * @return string[] Array of database fields like ['patients.first_name']
	 */
	public function searchByToFields($searchByString)
	{
		if (!$searchByString) {
			$searchByString = $this->default;
		}
				
		$parts = array_map('trim', explode(',', $searchByString));
		$result = [];
		foreach ($parts as $part) {
			if (isset($this->combinedFields[$part])) {
				$fields = $this->combinedFields[$part];
			}
			elseif (in_array($part, $this->basicFields)) {
				$fields = [$part];
			}
			else {
				throw new \Exception("Incorrect field $part.");
			}
			
			foreach ($fields as $field) {
				if (!in_array($field, $result)) {
					$result[] = $field;
				}
			}
		}
		
		return $result;
	}
}
