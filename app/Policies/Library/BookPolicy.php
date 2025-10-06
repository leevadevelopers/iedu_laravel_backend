<?php

namespace App\Policies\Library;

use App\Models\V1\Library\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('library.books.view');
    }

    public function view(User $user, Book $book): bool
    {
        if (!$user->hasPermissionTo('library.books.view')) {
            return false;
        }

        // Check visibility rules
        if ($book->visibility === 'public') {
            return true;
        }

        if ($book->visibility === 'tenant') {
            return $book->tenant_id === $user->tenant_id;
        }

        if ($book->visibility === 'restricted') {
            return in_array($user->tenant_id, $book->restricted_tenants ?? []);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['library.books.create', 'library.manage']);
    }

    public function update(User $user, Book $book): bool
    {
        if (!$user->hasAnyPermission(['library.books.update', 'library.manage'])) {
            return false;
        }

        // Only the tenant that created can update
        return $book->tenant_id === $user->tenant_id || $book->visibility === 'public';
    }

    public function delete(User $user, Book $book): bool
    {
        if (!$user->hasAnyPermission(['library.books.delete', 'library.manage'])) {
            return false;
        }

        return $book->tenant_id === $user->tenant_id;
    }
}
