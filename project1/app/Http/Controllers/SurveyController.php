<?php

namespace App\Http\Controllers;

use App\Question;
use App\Services\TextHelper;
use App\Subquestion;
use App\Survey;
use App\Tag;
use Auth;
use DB;
use Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Mail;
use Illuminate\Support\Facades\Hash;

/**
 * @Resource("Surveys", uri="/v1/surveys")
 *
 * @resource Surveys
 */
class SurveyController extends Controller
{
	public function __construct(\Dingo\Api\Http\Request $request)
	{
		$this->limit = $request->get('limit', 10);
		$this->offset = $request->get('offset', 0);
		$this->direction = $request->get('direction', 'ASC');
		$this->search = $request->get('search', '');
		//php artisan api:docs doesn't like search_by, so accept searchby
		$this->searchBy = $request->get('search_by', $request->get('searchby', ''));
		$this->tags = $request->get('tags', '');
		$this->sort = $request->get('sort', 'name');

		$this->validate($request, [
			'sort' => Rule::in(['name', 'author', 'count_questions'])
		]);
	}

	/**
	 * Converts the search_by string to an array of database fields,
	 * throwing an exception if an impossible field is passed.
	 *
	 * @param string Comma-separated list of fields
	 * @return string[] Array of database fields like ['name']
	 */
	private function searchByToFields($searchByString)
	{
		$parser = resolve('App\Services\SearchByParser');

		$parser->basicFields = [
			'surveys.name',
			'surveys.description',
			'authors.first_name',
			'authors.last_name',
		];
		$parser->combinedFields = [
			'all' => $parser->basicFields,
			'authors.full_name' => [
				'authors.first_name',
				'authors.last_name'
			]
		];
		$parser->default = 'all';

		return $parser->searchByToFields($searchByString);
	}

	/**
	 * Get surveys list.
	 *
	 * Get surveys list datas.
	 *
	 * @Get("/")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("search", type="string", description="Search surveys by name or description. Note that this field does NOT search by tags.", default="can be empty"),
	 *      @Parameter("tags", type="string", description="Comma-separated list of tag names, tag name 1,tag name 2. If tag name contains a comma, it should be replaced with _ (which also acts as a general-purpose wildcard)", default="can be empty"),
	 *      @Parameter("searchby", type="string", description="Comma-separated list of surveys.name, surveys.description, authors.full_name, authors.first_name, authors.last_name, all; not used if search is empty", default="all"),
	 *      @Parameter("offset", type="integer", description="The page of results to view.", default=1),
	 *      @Parameter("limit", type="integer", description="The amount of results per page.", default=10),
	 *      @Parameter("sort", type="string", description="Name column for sorting [name,author,count_questions]", default="name"),
	 *      @Parameter("direction", type="string", description="Name column for sorting [desc,asc]", default="desc")
	 * })
	 * @Response(200, body={"data":{{"id":1,"name":"Basic patient profile","description":"Basic Patient Privacy Consents (BPPC) provides a mechanism to record the patient privacy consent(s) and a method for Content Consumers to use to enforce the privacy consent appropriate to the use. This profile complements XDS by describing a mechanism whereby an XDS Affinity Domain can develop and implement multiple privacy policies, and describes how that mechanism can be integrated with the access control mechanisms supported by the XDS Actors (e.g. EHR systems). ","is_active":0,"modified_by":0,"count_questions":8,"author":null,"tags":{{"id":4,"name":"asd"},{"id":5,"name":"test1"}}},{"id":2,"name":"SVF Processing","description":"Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.","is_active":0,"modified_by":0,"count_questions":3,"author":null,"tags":{{"id":4,"name":"asd"}}}},"total":2})
	 *
	 * @return JSON patients details
	 */
	public function index(Request $request)
	{
		$user = Auth::user();
		DB::enableQueryLog();
		if ($this->search) {
			$words = array_map('trim', explode(" ", $this->search));
			$searchFields = $this->searchByToFields($this->searchBy);

			$prepareQuery = Survey::with('tags', 'questions', 'author')
				->leftJoin('users AS authors', 'authors.id', '=', 'surveys.modified_by');

			foreach ($words as $keyword) {
				$prepareQuery->where(function ($q) use ($keyword, $searchFields) {
					foreach ($searchFields as $searchField) {
						$q->orWhere($searchField, "LIKE", '%' . addcslashes($keyword, '%_') . '%');
					}
				});
			}
		} else {
			$prepareQuery = Survey::with('tags', 'questions', 'author');
		}

		if ($this->tags) {
			$tagNames = array_filter(array_map('trim', explode(',', $this->tags)));
			$tagIds = DB::table('tags')->where(function ($q) use ($tagNames) {
				foreach ($tagNames as $tagName) {
					$q->orWhere('tags.name', "LIKE", "%$tagName%");
				}
			})->pluck('id')->toArray();

			foreach ($tagIds as $tagId) {
				$prepareQuery->leftJoin("surveys_tags AS surveys_tags_{$tagId}", function ($join) use ($tagId) {
					$join->on("surveys_tags_{$tagId}.survey_id", '=', 'surveys.id');
					$join->on("surveys_tags_{$tagId}.tag_id", '=', DB::raw($tagId));
				})->whereNotNull("surveys_tags_{$tagId}.id");

			}
		}

		//general part
		$prepareQuery
			->selectRaw('surveys.*, (SELECT count(surveys_questions.id) from surveys_questions  where surveys.id = surveys_questions.survey_id AND surveys_questions.question_id ) as count_questions')
			->leftJoin('user_ignore_survey', function ($q) use ($user) {
				$q->on('user_ignore_survey.survey_id', '=', 'surveys.id');
				$q->on('user_ignore_survey.user_id', '=', DB::raw($user->id));
			})
			->leftJoin('user_survey', function ($q) use ($user) {
				$q->on('user_survey.survey_id', '=', 'surveys.id');
				$q->on('user_survey.user_id', '=', DB::raw($user->id));
			})
			->leftJoin('affiliate_survey', 'affiliate_survey.survey_id', '=', 'surveys.id')
			->whereNested(function ($q) use ($user) {
				$q->where('affiliate_survey.affiliate_id', '=', $user->affiliate_id)
					->orWhereNotNull('user_survey.id');
			})
			->whereNull('user_ignore_survey.id');

		//sort part

		$prepareQueryTotal = clone $prepareQuery;
		$total = $prepareQueryTotal->count(DB::raw('DISTINCT surveys.id'));
		
		$prepareQuery->limit($this->limit)->offset($this->offset);
		
		if ($this->sort === 'count_questions') {
			$prepareQuery
				->groupBy(DB::raw(implode(",", array_map(function ($a) {
					return "surveys." . $a;
				}, Schema::getColumnListing('surveys')))))
				->orderBy($this->sort, $this->direction);
		}
		elseif ($this->sort === 'author') {
			$prepareQuery->leftJoin('users', 'surveys.modified_by', '=', 'users.id')
				->orderBy('users.first_name', $this->direction);
		} else {
			$prepareQuery->orderBy($this->sort, $this->direction);
		}

		$prepareQuery->groupBy(DB::raw(implode(",", array_map(function ($a) {
			return "surveys." . $a;
		}, Schema::getColumnListing('surveys')))));

		//result
		$surveys = $prepareQuery->get(['surveys.*']);

		//prepare datas
		foreach ($surveys as $survey) {
			unset($survey->questions);

			$tagsIds = array_column($survey['tags']->toArray(), 'id');

			foreach ($survey['questions'] as $k2 => $question) {
				$question['tags'] = Question::find($question['id'])->tags;
				$qTagsIds = array_column($question['tags']->toArray(), 'id');
				$subQuestions = Question::find($question['id'])->subquestions;
				foreach ($subQuestions as $k3 => $sub) {
					$sub['tags'] = Subquestion::find($sub['id'])->tags;
					foreach ($sub['tags'] as $tag) {
						if (!in_array($tag['id'], $qTagsIds)) {
							$question['tags'] [] = $tag;
							$qTagsIds [] = $tag['id'];
						}
					}
				}
				foreach ($question['tags'] as $tag) {
					if (!in_array($tag['id'], $tagsIds)) {
						$survey['tags'] [] = $tag;
						$tagsIds [] = $tag['id'];
					}
				}
			}
		}

		return response()->success($surveys, $total);
	}

	/**
	 * Get list of tags.
	 *
	 * Get list of tags, optionally filtered by the tag name.
	 *
	 * @Get("/tags")
	 * @Versions({"v1"})
	 * @Parameters({
	 *      @Parameter("search", type="string", description="Search tags by by tag name.", default="can be empty"),
	 *      @Parameter("offset", type="integer", description="The page of results to view.", default=1),
	 *      @Parameter("limit", type="integer", description="The amount of results per page.", default=10)
	 * })
	 * @Response(200, body={"errors":false,"data":{{"id":1,"name":"Rating 1","category_id":1,"modified_by":1,"created_at":"2017-06-21 14:39:11","updated_at":"2017-06-21 14:39:11"},{"id":2,"name":"Rating 2","category_id":1,"modified_by":1,"created_at":"2017-06-21 14:39:11","updated_at":"2017-06-21 14:39:11"}},"total":4})
	 *
	 * @return JSON patients details
	 */
	public function getTags(Request $request)
	{
		if ($this->search) {
			$keyword = $this->search;

			$tagQuery = Tag::where('name', 'LIKE', "%$keyword%");
		} else {
			$tagQuery = Tag::where(DB::raw('1'), '=', '1');
		}

		$tagQueryTotal = clone $tagQuery;
		$total = $tagQueryTotal->offset(0)->limit(1)->count();
		$tags = $tagQuery->limit($this->limit)->offset($this->offset)->get();

		return response()->success($tags, $total);
	}

	/**
	 * Get survey
	 *
	 * Get survey
	 *
	 * @Get("/:id")
	 * @Versions({"v1"})
	 * @Response(200,body={"id":30,"name":"General survey","description":"","is_active":1,"modified_by":1,"questions":{{"id":38,"name":"TEST QUEST","url":"google.com","subquestions":{{"id":79,"text":"Radio","question_id":38,"type":"Radio group","is_required":0,"answers":{"values":{"Yes","No","1"},"default":"No"},"modified_by":1,"description":"","files": {{"id": 11,"filename": "http://dev.admin.incs.tk/uploads/sub/8142d2f8a47628a314423b909b4dd4a4dcd7d40e/47263d3294f2346486793ed1c6e6b7776ad96b45.jpg"}}},{"id":80,"text":"Dropdown","question_id":38,"type":"Dropdown","is_required":1,"answers":{"values":{"choice1","choice2","choice3"},"default":null},"modified_by":1,"description":"","file":null},{"id":81,"text":"Checkbox","question_id":38,"type":"Checkboxes","is_required":0,"answers":{"values":{{"name":"check1","checked":"0"},{"name":"check2","checked":"1"},{"name":"check3","checked":"0"}},"default":null},"modified_by":1,"description":"","selected_answer":{"check1","check2"},"file":null},{"id":82,"text":"Text","question_id":38,"type":"Text","is_required":0,"answers":{"values":{},"default":"Test text"},"modified_by":1,"description":"","selected_answer":{"Some text"},"file":null},{"id":83,"text":"Scale","question_id":38,"type":"Scale","is_required":0,"answers":{"values":{1,100},"default":null},"modified_by":1,"description":"","selected_answer":{55},"file":null},{"id":84,"text":"Upload","question_id":38,"type":"Upload","is_required":0,"answers":{"values":{},"default":null},"modified_by":1,"description":"","selected_answer":{"http://dev.api.incs.tk/uploads/1/s-2_400x400.jpg"},"file":null},{"id":85,"text":"Digit","question_id":38,"type":"Digit field","is_required":0,"answers":{"values":{5},"default":null},"modified_by":1,"description":"","selected_answer":{5},"file":null}}}}})
	 * @return JSON
	 */
	public function getSurvey($id)
	{
	    /** @var Survey $survey */
		$survey = Survey::with('questions')->find($id);

		if (!$survey) {
			return response()->error(TextHelper::t("Survey not found"), 404);
		}

		$survey->orderQuestions();
        foreach ($survey->questions as $questions) {
			foreach ($questions->subquestions as $subquestion) {
				$subquestion->load('files');
				$subquestion->files->makeHidden(['subquestion_id', 'created_at', 'updated_at']);
			}
		}

		return response()->success($survey);
	}
}
