<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class LibraryPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $libraryPermissions = [
            // Collections
            'library.collections.view',
            'library.collections.create',
            'library.collections.update',
            'library.collections.delete',

            // Authors
            'library.authors.view',
            'library.authors.create',
            'library.authors.update',
            'library.authors.delete',

            // Publishers
            'library.publishers.view',
            'library.publishers.create',
            'library.publishers.update',
            'library.publishers.delete',

            // Books
            'library.books.view',
            'library.books.create',
            'library.books.update',
            'library.books.delete',

            // Book Files
            'library.book-files.view',
            'library.book-files.create',
            'library.book-files.update',
            'library.book-files.delete',
            'library.book-files.download',

            // Loans
            'library.loans.view',
            'library.loans.create',
            'library.loans.manage',
            'library.loans.request',
            'library.loans.delete',

            // Reservations
            'library.reservations.view',
            'library.reservations.create',
            'library.reservations.manage',

            // Incidents
            'library.incidents.view',
            'library.incidents.create',
            'library.incidents.resolve',

            // General
            'library.manage',
        ];

        foreach ($libraryPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        $this->command->info('Library permissions created successfully!');
    }
}
