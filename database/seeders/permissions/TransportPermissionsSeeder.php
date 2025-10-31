<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TransportPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Transport Module Permissions
        $transportPermissions = [
            // Transport Routes
            'transport.routes.view',
            'transport.routes.create',
            'transport.routes.edit',
            'transport.routes.delete',
            'transport.routes.manage',

            // Transport Vehicles
            'transport.vehicles.view',
            'transport.vehicles.create',
            'transport.vehicles.edit',
            'transport.vehicles.delete',
            'transport.vehicles.manage',

            // Transport Drivers
            'transport.drivers.view',
            'transport.drivers.create',
            'transport.drivers.edit',
            'transport.drivers.delete',
            'transport.drivers.manage',

            // Transport Subscriptions
            'transport.subscriptions.view',
            'transport.subscriptions.create',
            'transport.subscriptions.edit',
            'transport.subscriptions.delete',
            'transport.subscriptions.manage',

            // Transport Students
            'transport.students.view',
            'transport.students.assign',
            'transport.students.remove',
            'transport.students.manage',

            // Transport Reports
            'transport.reports.view',
            'transport.reports.export',
            'transport.reports.analytics',

            // Transport Notifications
            'transport.notifications.view',
            'transport.notifications.send',
            'transport.notifications.manage',

            // Transport Settings
            'transport.settings.view',
            'transport.settings.edit',
            'transport.settings.manage',
        ];

        // Create permissions
        foreach ($transportPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Legacy alias permissions used by controllers' middleware
        $legacyAliasPermissions = [
            // Generic transport CRUD aliases
            'view-transport',
            'create-transport',
            'edit-transport',
            'delete-transport',

            // Subscription-specific aliases used in TransportSubscriptionController
            'view-transport-subscriptions',
            'create-transport-subscriptions',
            'edit-transport-subscriptions',
            'delete-transport-subscriptions',
        ];

        foreach ($legacyAliasPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        // Create roles and assign permissions
        $this->createRoles();

        // Ensure legacy alias permissions are granted to roles that need them
        $this->assignLegacyAliasesToRoles();
    }

    private function createRoles(): void
    {
        // Transport Administrator - Full access to transport module
        $transportAdmin = Role::firstOrCreate([
            'name' => 'transport_admin',
            'guard_name' => 'api'
        ]);

        $transportAdmin->givePermissionTo([
            'transport.routes.view',
            'transport.routes.create',
            'transport.routes.edit',
            'transport.routes.delete',
            'transport.routes.manage',
            'transport.vehicles.view',
            'transport.vehicles.create',
            'transport.vehicles.edit',
            'transport.vehicles.delete',
            'transport.vehicles.manage',
            'transport.drivers.view',
            'transport.drivers.create',
            'transport.drivers.edit',
            'transport.drivers.delete',
            'transport.drivers.manage',
            'transport.subscriptions.view',
            'transport.subscriptions.create',
            'transport.subscriptions.edit',
            'transport.subscriptions.delete',
            'transport.subscriptions.manage',
            'transport.students.view',
            'transport.students.assign',
            'transport.students.remove',
            'transport.students.manage',
            'transport.reports.view',
            'transport.reports.export',
            'transport.reports.analytics',
            'transport.notifications.view',
            'transport.notifications.send',
            'transport.notifications.manage',
            'transport.settings.view',
            'transport.settings.edit',
            'transport.settings.manage',
        ]);

        // Transport Manager - Management level access
        $transportManager = Role::firstOrCreate([
            'name' => 'transport_manager',
            'guard_name' => 'api'
        ]);

        $transportManager->givePermissionTo([
            'transport.routes.view',
            'transport.routes.create',
            'transport.routes.edit',
            'transport.vehicles.view',
            'transport.vehicles.create',
            'transport.vehicles.edit',
            'transport.drivers.view',
            'transport.drivers.create',
            'transport.drivers.edit',
            'transport.subscriptions.view',
            'transport.subscriptions.create',
            'transport.subscriptions.edit',
            'transport.students.view',
            'transport.students.assign',
            'transport.students.remove',
            'transport.reports.view',
            'transport.reports.export',
            'transport.notifications.view',
            'transport.notifications.send',
            'transport.settings.view',
        ]);

        // Transport Driver - Limited access for drivers
        $transportDriver = Role::firstOrCreate([
            'name' => 'transport_driver',
            'guard_name' => 'api'
        ]);

        $transportDriver->givePermissionTo([
            'transport.routes.view',
            'transport.vehicles.view',
            'transport.students.view',
            'transport.notifications.view',
        ]);

        // Transport Coordinator - Coordination level access
        $transportCoordinator = Role::firstOrCreate([
            'name' => 'transport_coordinator',
            'guard_name' => 'api'
        ]);

        $transportCoordinator->givePermissionTo([
            'transport.routes.view',
            'transport.routes.create',
            'transport.routes.edit',
            'transport.vehicles.view',
            'transport.vehicles.create',
            'transport.vehicles.edit',
            'transport.drivers.view',
            'transport.drivers.create',
            'transport.drivers.edit',
            'transport.subscriptions.view',
            'transport.subscriptions.create',
            'transport.subscriptions.edit',
            'transport.students.view',
            'transport.students.assign',
            'transport.students.remove',
            'transport.reports.view',
            'transport.reports.export',
            'transport.notifications.view',
            'transport.notifications.send',
        ]);

        // Parent - Limited access for parents
        $parent = Role::firstOrCreate([
            'name' => 'parent',
            'guard_name' => 'api'
        ]);

        $parent->givePermissionTo([
            'transport.routes.view',
            'transport.subscriptions.view',
            'transport.students.view',
            'transport.notifications.view',
        ]);
    }

    private function assignLegacyAliasesToRoles(): void
    {
        // Map legacy aliases to roles by responsibility level
        $adminAliases = [
            'view-transport', 'create-transport', 'edit-transport', 'delete-transport',
            'view-transport-subscriptions', 'create-transport-subscriptions', 'edit-transport-subscriptions', 'delete-transport-subscriptions',
        ];

        $managerAliases = [
            'view-transport', 'create-transport', 'edit-transport',
            'view-transport-subscriptions', 'create-transport-subscriptions', 'edit-transport-subscriptions',
        ];

        $coordinatorAliases = [
            'view-transport', 'create-transport', 'edit-transport',
            'view-transport-subscriptions', 'create-transport-subscriptions', 'edit-transport-subscriptions',
        ];

        $driverAliases = [
            'view-transport',
        ];

        $parentAliases = [
            'view-transport',
            'view-transport-subscriptions',
        ];

        $roles = [
            'Transport Administrator' => $adminAliases,
            'Transport Manager' => $managerAliases,
            'Transport Coordinator' => $coordinatorAliases,
            'Transport Driver' => $driverAliases,
            'Parent' => $parentAliases,
        ];

        foreach ($roles as $roleName => $aliases) {
            $role = Role::where('name', $roleName)->where('guard_name', 'api')->first();
            if ($role) {
                $role->givePermissionTo($aliases);
            }
        }
    }
}
