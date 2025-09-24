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

        // Create roles and assign permissions
        $this->createRoles();
    }

    private function createRoles(): void
    {
        // Transport Administrator - Full access to transport module
        $transportAdmin = Role::firstOrCreate([
            'name' => 'Transport Administrator',
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
            'name' => 'Transport Manager',
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
            'name' => 'Transport Driver',
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
            'name' => 'Transport Coordinator',
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
            'name' => 'Parent',
            'guard_name' => 'api'
        ]);

        $parent->givePermissionTo([
            'transport.routes.view',
            'transport.subscriptions.view',
            'transport.students.view',
            'transport.notifications.view',
        ]);
    }
}
