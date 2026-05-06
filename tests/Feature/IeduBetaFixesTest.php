<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke tests that avoid migrations (SQLite in-memory cannot apply all project migrations, e.g. fulltext).
 * Run fuller API tests against a MySQL `testing` database in CI when available.
 */
class IeduBetaFixesTest extends TestCase
{
    public function test_forgot_password_rejects_non_email_identifier(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => '+258841234567',
            'type' => 'email',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_password_validation_requires_token_identifier_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
    }

    public function test_invoice_bulk_issue_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/finance/invoices/bulk-issue', [
            'invoice_ids' => [1, 2],
        ]);

        $response->assertUnauthorized();
    }
}
