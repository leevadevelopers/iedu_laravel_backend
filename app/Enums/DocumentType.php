<?php

namespace App\Enums;

enum DocumentType: string
{
    case BIRTH_CERTIFICATE = 'birth_certificate';
    case VACCINATION_RECORDS = 'vaccination_records';
    case PREVIOUS_TRANSCRIPTS = 'previous_transcripts';
    case IDENTIFICATION = 'identification';
    case MEDICAL_RECORDS = 'medical_records';
    case SPECIAL_EDUCATION = 'special_education';
    case ENROLLMENT_FORM = 'enrollment_form';
    case EMERGENCY_CONTACTS = 'emergency_contacts';
    case PHOTO_PERMISSION = 'photo_permission';
    case OTHER = 'other';

    /**
     * Get all document type values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get human-readable label for document type
     */
    public function getLabel(): string
    {
        return match($this) {
            self::BIRTH_CERTIFICATE => 'Birth Certificate',
            self::VACCINATION_RECORDS => 'Vaccination Records',
            self::PREVIOUS_TRANSCRIPTS => 'Previous Transcripts',
            self::IDENTIFICATION => 'Identification',
            self::MEDICAL_RECORDS => 'Medical Records',
            self::SPECIAL_EDUCATION => 'Special Education',
            self::ENROLLMENT_FORM => 'Enrollment Form',
            self::EMERGENCY_CONTACTS => 'Emergency Contacts',
            self::PHOTO_PERMISSION => 'Photo Permission',
            self::OTHER => 'Other',
        };
    }

    /**
     * Get all document types with labels
     */
    public static function options(): array
    {
        return array_map(
            fn($case) => ['value' => $case->value, 'label' => $case->getLabel()],
            self::cases()
        );
    }
}
