<?php
namespace App\Observers;

use App\Permission;
use App\PermissionRole;
use App\Role;

/**
 * Role observer
 */
class RoleObserver
{
    /**
     * After role has been created
     */
    public function created(Role $role)
    {
        $permissions = Permission::all();

        foreach ($permissions as $permission) {
            $permissionRole = new PermissionRole();
            $permissionRole->permission_id = $permission->id;
            $permissionRole->role_id = $role->id;
            $permissionRole->view = 0;
            $permissionRole->edit = 0;
            $permissionRole->create = 0;
            $permissionRole->delete = 0;
            $permissionRole->grant = 0;
            $permissionRole->waterfall = 0;
            $permissionRole->save();
        }
    }

    /**
     * On role deleting
     */
    public function deleting(Role $role)
    {
        PermissionRole::where('role_id', '=', $role->id)->delete();
    }
}
