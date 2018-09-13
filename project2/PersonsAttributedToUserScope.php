<?php

namespace App\Scopes;

use App\Company;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class PersonsAttributedToUserScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        /** @var User $user */
        $user = Auth::user();
        if ($user->admin) {
            return;
        }
        $userIdList = array_merge([$user->id], Company::userIdListConnectedWithMyCompanies($user));

        $builder->whereIn('user_id', $userIdList);
    }
}
