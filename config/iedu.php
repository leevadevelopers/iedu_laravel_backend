<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Signup idempotency (Idempotency-Key header)
    |--------------------------------------------------------------------------
    |
    | How long to remember successful signup responses for replay (seconds).
    |
    */

    'signup_idempotency_ttl' => (int) env('IEDU_SIGNUP_IDEMPOTENCY_TTL', 600),

    /*
    | Only for automated tests: throw after User::create inside signup transaction.
    */
    'testing_abort_after_user_create' => (bool) env('IEDU_TEST_ABORT_AFTER_USER_CREATE', false),

];
