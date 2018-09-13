<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'email_verified' => '1',
                'created_at' => "2018-06-01 10:00:00",
                'affiliate_id' => '1',
                'title' => 'Mr.',
                'first_name' => 'Admin',
                'last_name' => 'Admin'
            ],[
                'id' => 2,
                'username' => 'user1',
                'email' => 'user1@test.com',
                'password' => bcrypt('password'),
                'email_verified' => '1',
                'created_at' => "2018-06-01 10:00:00",
                'affiliate_id' => '1',
                'title' => 'Mr.',
                'first_name' => 'test',
                'last_name' => 'test'
            ], [
                'id' => 3,
                'username' => 'user2@test.com',
                'email' => 'user2@test.com',
                'password' => bcrypt('password'),
                'email_verified' => '1',
                'created_at' => "2018-06-01 10:00:00",
                'affiliate_id' => '1',
                'title' => 'Mr.',
                'first_name' => 'test 2',
                'last_name' => 'test 2'
            ]
        ]);
    }
}
