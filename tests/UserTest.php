<?php

use Tabulate\DB\Tables\Users;

class UserTest extends TestBase
{

    /**
     * @test
     */
    public function register()
    {
        $users = new Users($this->db);
        $user = $users->saveRecord(['name' => 'Test User']);
        $this->assertEquals('Test User', $user->name());
        $this->assertEquals(false, $user->verified());
    }
}
