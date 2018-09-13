<?php

namespace App\Policies;

use App\Company;
use App\Permission;
use App\User;
use App\Person;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     *
     * @return bool
     */
    public function before(User $user)
    {
        if ($user->admin) {
            return true;
        }
    }

    /**
     * @param User   $user
     * @param Person $person
     *
     * @return bool
     */
    protected function personBelongsToUser(User $user, Person $person) {
        return Person::whereIn('user_id', Company::userIdListConnectedWithMyCompanies($user))->where('id', $person->id)->exists();
    }

    /**
     * Determine whether the user can create the person.
     *
     * @param User $user
     *
     * @return bool
     */
    public function create(User $user)
    {
        $canCreate = Permission::canCRUD(Permission::CRUD_PERMISSION_PERSON, 'create', $user);

        return $canCreate;
    }

    /**
     * Determine whether the user can view the person.
     *
     * @param  \App\User  $user
     * @param  \App\Person  $person
     * @return mixed
     */
    public function view(User $user, Person $person)
    {
        $canView = Permission::canCRUD(Permission::CRUD_PERMISSION_PERSON, 'read', $user);
        return $canView && $this->personBelongsToUser($user, $person);
    }

    /**
     * Determine whether the user can view the person.
     *
     * @param  \App\User  $user
     * @param  \App\Person  $person
     * @return mixed
     */
    public function edit(User $user, Person $person)
    {
        $canUpdate = Permission::canCRUD(Permission::CRUD_PERMISSION_PERSON, 'update', $user);
        return $canUpdate && $this->personBelongsToUser($user, $person);
    }

    /**
     * Determine whether the user can destroy the person.
     *
     * @param  \App\User  $user
     * @param  \App\Person  $person
     * @return mixed
     */
    public function destroy(User $user, Person $person)
    {
        $canDestroy = Permission::canCRUD(Permission::CRUD_PERMISSION_PERSON, 'delete', $user);
        return $canDestroy && $this->personBelongsToUser($user, $person);
    }
}
