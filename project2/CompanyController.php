<?php

namespace App\Http\Controllers\Api\Admin;

use App\Company;
use App\Http\Requests\CompanyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    const COMPANIES_PER_PAGE = 10;

    /**
     * @return array
     */
    public static function companyList()
    {
        $companies = Company::orderBy('name')->get()->pluck('name', 'id')->toArray();
        $return = [];
        foreach ($companies as $id => $company) {
            $return[] = ['value' => $id, 'label' => $company];
        }
        return $return;
    }

    /**
     * @param Request $request
     *
     * @return LengthAwarePaginator|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function index(Request $request)
    {
        /** @var LengthAwarePaginator $returnData */
        $perPage = (int)$request->get('per_page', self::COMPANIES_PER_PAGE);
        $sortby = $request->get('sortby', []);

        $companyQuery = Company::query();
        if (!empty($sortby)) {
            foreach ($sortby as $sortItem) {
                $sortByEncoded = json_decode($sortItem);
                $companyQuery->orderBy($sortByEncoded->id, $sortByEncoded->desc ? 'desc' : 'asc');
            }
        } else {
            $companyQuery->orderBy('id', 'desc');
        }

        $search = $request->get('search');
        if ($search) {
            $companyQuery = $companyQuery
                ->where('name', 'like', "%{$search}%");
        }

        $returnData = $companyQuery->paginate($perPage);
        $returnData = $returnData->toArray();
        $returnData['sortby'] = $sortby;

        return $returnData;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  CompanyRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CompanyRequest $request)
    {
        $company = new Company();
        $company->name = $request->name;
        if ($company->save()) {
            return response()
                ->json([
                    'company' => $company,
                    'message' => "Company has been added",
                ]);
        }
    }

    /**
     * @param Company $company
     *
     * @return array
     */
    public function edit(Company $company)
    {
        return compact('company');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  CompanyRequest  $request
     * @param  \App\Company  $company
     * @return \Illuminate\Http\Response
     */
    public function update(CompanyRequest $request, Company $company)
    {
        $company->name = $request->name;
        if ($company->save()) {
            return response()
                ->json([
                    'company' => $company,
                    'message' => "Company has been saved",
                ]);
        }
    }

    /**
     * @param Company $company
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy(Company $company)
    {
        try {
            DB::beginTransaction();
            $company->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return response()
            ->json([
                'company' => $company,
                'message' => "Company has been deleted",
            ]);
    }
}
