<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\SchoolUser;
use App\Models\Settings\Tenant;
use Tests\TestCase;

class SchoolUserTest extends TestCase
{

    public function test_school_user_model_instantiation()
    {
        // Test that the SchoolUser model can be instantiated
        $schoolUser = new SchoolUser();

        $this->assertInstanceOf(SchoolUser::class, $schoolUser);
        $this->assertEquals('school_users', $schoolUser->getTable());
    }

    public function test_user_schools_relationship_definition()
    {
        // Test that the User model has the schools relationship defined
        $user = new User();

        $this->assertTrue(method_exists($user, 'schools'));
        $this->assertTrue(method_exists($user, 'activeSchools'));
    }

    public function test_school_users_relationship_definition()
    {
        // Test that the School model has the users relationship defined
        $school = new School();

        $this->assertTrue(method_exists($school, 'users'));
        $this->assertTrue(method_exists($school, 'activeUsers'));
    }

    public function test_school_user_validation_methods()
    {
        // Test that the SchoolUser model has validation methods
        $schoolUser = new SchoolUser();

        $this->assertTrue(method_exists($schoolUser, 'isActive'));
        $this->assertTrue(method_exists($schoolUser, 'isValid'));
        $this->assertTrue(method_exists($schoolUser, 'hasPermission'));
        $this->assertTrue(method_exists($schoolUser, 'hasAnyPermission'));
        $this->assertTrue(method_exists($schoolUser, 'hasAllPermissions'));
    }

    public function test_school_user_fillable_attributes()
    {
        // Test that the SchoolUser model has the correct fillable attributes
        $schoolUser = new SchoolUser();

        $expectedFillable = [
            'school_id',
            'user_id',
            'role',
            'status',
            'start_date',
            'end_date',
            'permissions',
        ];

        $this->assertEquals($expectedFillable, $schoolUser->getFillable());
    }
}
