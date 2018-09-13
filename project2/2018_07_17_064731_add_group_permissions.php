<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

use Carbon\Carbon;
use \App\Group;
use \App\Permission;

class AddGroupPermissions extends Migration
{
    /**
     * @throws Exception
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        DB::table('permissions')->insert([
            [
                'group_id' => null,
                'name' => Permission::CRUD_PERMISSION_GROUP,
                'description' => 'Groups',
                'slug' => Permission::CRUD_PERMISSION_GROUP,
                'create' => false,
                'read' => false,
                'update' => false,
                'delete' => false,
                'grant' => false,

                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);

        Schema::disableForeignKeyConstraints();
        Schema::table('groups', function (Blueprint $table) {
            $table->integer('created_by')->unsigned()->nullable();
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
        });

        DB::beginTransaction();

        /** @var Group [] $groups */
        $groups = Group::all();
        foreach ($groups as $group) {
            if ($group->is_admin_group || $group->is_company_admin_group) {
                Permission::assignDefaultPermissions($group->id, true, true, true, true, false);
            } else {
                Permission::assignDefaultPermissions($group->id);
            }
        }

        $adminGroup = Group::admin()->with('users')->first();
        $admin = $adminGroup->users[0];
        $defaultGroups = Group::whereDefault(1)->get();
        foreach ($defaultGroups as $defaultGroup) {
            $defaultGroup->created_by = $admin->id;
            $defaultGroup->save();
        }

        DB::commit();
    }

    /**
     * @throws Exception
     */
    public function down()
    {
        Permission::removePermissions(NULL, Permission::CRUD_PERMISSION_GROUP);

        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign('groups_created_by_foreign');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
}
