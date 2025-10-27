<?php

namespace App\Http\Constants;

class ErrorCodes
{
    // Validation errors
    public const VALIDATION_FAILED = 'VALIDATION_FAILED';

    // Authorization errors
    public const UNAUTHORIZED_ACCESS = 'UNAUTHORIZED_ACCESS';
    public const TENANT_MISMATCH = 'TENANT_MISMATCH';
    public const SCHOOL_ACCESS_DENIED = 'SCHOOL_ACCESS_DENIED';

    // Resource errors
    public const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    public const DUPLICATE_RESOURCE = 'DUPLICATE_RESOURCE';
    public const ACTIVE_DEPENDENCIES = 'ACTIVE_DEPENDENCIES';

    // Operation errors
    public const OPERATION_FAILED = 'OPERATION_FAILED';
    public const DATABASE_ERROR = 'DATABASE_ERROR';

    // Profile errors
    public const PROFILE_UPDATE_FAILED = 'PROFILE_UPDATE_FAILED';
    public const IDENTIFIER_ALREADY_EXISTS = 'IDENTIFIER_ALREADY_EXISTS';
    public const REAUTHENTICATION_REQUIRED = 'REAUTHENTICATION_REQUIRED';
    public const INVALID_PASSWORD = 'INVALID_PASSWORD';

    // General errors
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';
    public const FORBIDDEN = 'FORBIDDEN';
    public const SERVER_ERROR = 'SERVER_ERROR';

    /**
     * Get all error codes
     */
    public static function all(): array
    {
        return [
            self::VALIDATION_FAILED,
            self::UNAUTHORIZED_ACCESS,
            self::TENANT_MISMATCH,
            self::SCHOOL_ACCESS_DENIED,
            self::RESOURCE_NOT_FOUND,
            self::DUPLICATE_RESOURCE,
            self::ACTIVE_DEPENDENCIES,
            self::OPERATION_FAILED,
            self::DATABASE_ERROR,
            self::PROFILE_UPDATE_FAILED,
            self::IDENTIFIER_ALREADY_EXISTS,
            self::REAUTHENTICATION_REQUIRED,
            self::INVALID_PASSWORD,
            self::UNAUTHENTICATED,
            self::FORBIDDEN,
            self::SERVER_ERROR,
        ];
    }
}
