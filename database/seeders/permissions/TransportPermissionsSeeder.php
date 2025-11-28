<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

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

        foreach ($transportPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
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
    }
}
