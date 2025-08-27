# Student Information System (SIS) Controllers

This document provides comprehensive documentation for all the controllers created to manage the Student Information System, including Form Engine & Workflow integration.

## Overview

The SIS controllers provide a complete API for managing:
- **Students**: Core student information and academic records
- **Student Documents**: Document management and verification workflows
- **Student Enrollments**: Enrollment tracking and academic progression
- **Family Relationships**: Family member management and permissions
- **Schools**: School information and configuration
- **Academic Years**: Academic year planning and management
- **Academic Terms**: Term-based academic structure

All controllers integrate with the Form Engine for dynamic form processing and Workflow Service for automated business processes.

## Controllers Structure

```
app/Http/Controllers/API/V1/
├── Student/
│   ├── StudentController.php
│   ├── StudentDocumentController.php
│   ├── StudentEnrollmentController.php
│   └── FamilyRelationshipController.php
└── School/
    ├── SchoolController.php
    ├── AcademicYearController.php
    └── AcademicTermController.php
```

## 1. Student Controller

**File**: `app/Http/Controllers/API/V1/Student/StudentController.php`

### Features
- Complete CRUD operations for student management
- Student enrollment with Form Engine integration
- Student transfer workflows
- Bulk operations (promotion, enrollment)
- Academic summary and analytics
- Search and filtering capabilities

### Key Methods

#### Core CRUD
- `index()` - List students with filters and pagination
- `store()` - Create new student with enrollment workflow
- `show()` - Display student details with relationships
- `update()` - Update student information
- `destroy()` - Soft delete student

#### Special Operations
- `academicSummary()` - Get comprehensive academic overview
- `transfer()` - Transfer student to another school
- `bulkPromote()` - Promote multiple students
- `enrollmentStats()` - Get enrollment statistics

#### Form Engine Integration
- Processes `student_registration` forms
- Creates form instances for audit trails
- Starts `student_enrollment` workflow

#### Workflow Integration
- **Student Enrollment Workflow**: 4-step process
  - Document verification
  - Parent consent
  - Medical assessment
  - Final approval
- **Student Transfer Workflow**: 4-step process
  - Document verification
  - New school approval
  - Records transfer
  - Final confirmation

### API Endpoints
```
GET    /api/v1/students                    # List students
POST   /api/v1/students                    # Create student
GET    /api/v1/students/{id}               # Get student
PUT    /api/v1/students/{id}               # Update student
DELETE /api/v1/students/{id}               # Delete student
GET    /api/v1/students/{id}/academic-summary
POST   /api/v1/students/{id}/transfer
POST   /api/v1/students/bulk/promote
GET    /api/v1/students/analytics/enrollment-stats
```

## 2. Student Document Controller

**File**: `app/Http/Controllers/API/V1/Student/StudentDocumentController.php`

### Features
- Document upload and management
- File storage with private access
- Document verification workflows
- Bulk status updates
- Expiry tracking and notifications
- Search and filtering

### Key Methods

#### Core Operations
- `index()` - List documents with filters
- `store()` - Upload document with verification workflow
- `show()` - Display document details
- `update()` - Update document information
- `destroy()` - Delete document and file

#### File Operations
- `uploadFile()` - Handle file uploads
- `download()` - Generate download links
- `getByStudent()` - Get documents for specific student

#### Special Features
- `getRequiringAttention()` - Find documents needing review
- `bulkUpdateStatus()` - Update multiple documents
- `getStatistics()` - Document analytics

#### Form Engine Integration
- Processes `document_upload` forms
- Creates form instances for tracking
- Starts `document_verification` workflow

#### Workflow Integration
- **Document Verification Workflow**: 4-step process
  - Initial review
  - Content verification
  - Approval
  - Final validation

### API Endpoints
```
GET    /api/v1/student-documents                    # List documents
POST   /api/v1/student-documents                    # Upload document
GET    /api/v1/student-documents/{id}               # Get document
PUT    /api/v1/student-documents/{id}               # Update document
DELETE /api/v1/student-documents/{id}               # Delete document
POST   /api/v1/student-documents/upload-file        # Upload file
GET    /api/v1/student-documents/{id}/download      # Download file
GET    /api/v1/student-documents/by-student/{id}    # Student documents
GET    /api/v1/student-documents/requiring-attention
POST   /api/v1/student-documents/bulk-update-status
GET    /api/v1/student-documents/statistics
```

## 3. Student Enrollment Controller

**File**: `app/Http/Controllers/API/V1/Student/StudentEnrollmentController.php`

### Features
- Enrollment record management
- Academic progression tracking
- Bulk enrollment operations
- Transfer management
- Enrollment analytics and trends

### Key Methods

#### Core Operations
- `index()` - List enrollment records
- `store()` - Create enrollment with workflow
- `show()` - Display enrollment details
- `update()` - Update enrollment information
- `destroy()` - Remove enrollment record

#### Special Operations
- `getByStudent()` - Get student's enrollment history
- `getCurrentEnrollment()` - Get current enrollment status
- `bulkEnroll()` - Enroll multiple students
- `bulkTransfer()` - Transfer multiple students

#### Analytics
- `getStatistics()` - Enrollment statistics
- `getEnrollmentTrends()` - Time-based trends

#### Form Engine Integration
- Processes `student_enrollment` forms
- Creates form instances for tracking
- Starts `enrollment_processing` workflow

#### Workflow Integration
- **Enrollment Processing Workflow**: 4-step process
  - Document verification
  - Academic assessment
  - Parent consent
  - Final approval

### API Endpoints
```
GET    /api/v1/student-enrollments                    # List enrollments
POST   /api/v1/student-enrollments                    # Create enrollment
GET    /api/v1/student-enrollments/{id}               # Get enrollment
PUT    /api/v1/student-enrollments/{id}               # Update enrollment
DELETE /api/v1/student-enrollments/{id}               # Delete enrollment
GET    /api/v1/student-enrollments/by-student/{id}    # Student enrollments
GET    /api/v1/student-enrollments/current/{id}       # Current enrollment
POST   /api/v1/student-enrollments/bulk/enroll        # Bulk enroll
POST   /api/v1/student-enrollments/bulk/transfer      # Bulk transfer
GET    /api/v1/student-enrollments/statistics         # Statistics
GET    /api/v1/student-enrollments/trends             # Trends
```

## 4. Family Relationship Controller

**File**: `app/Http/Controllers/API/V1/Student/FamilyRelationshipController.php`

### Features
- Family member relationship management
- Contact permission management
- Emergency contact handling
- Authorized pickup management
- Bulk relationship creation

### Key Methods

#### Core Operations
- `index()` - List family relationships
- `store()` - Create relationship with verification
- `show()` - Display relationship details
- `update()` - Update relationship information
- `destroy()` - Remove relationship

#### Special Operations
- `getByStudent()` - Get student's family members
- `getPrimaryContact()` - Get primary contact
- `getEmergencyContacts()` - Get emergency contacts
- `getAuthorizedPickupPersons()` - Get pickup authorization
- `setPrimaryContact()` - Set primary contact
- `bulkCreate()` - Create multiple relationships

#### Search and Analytics
- `searchPotentialMembers()` - Find potential family members
- `getStatistics()` - Relationship analytics

#### Form Engine Integration
- Processes `family_relationship` forms
- Creates form instances for tracking
- Starts `relationship_verification` workflow

#### Workflow Integration
- **Relationship Verification Workflow**: 4-step process
  - Identity verification
  - Relationship proof
  - Background check
  - Final approval

### API Endpoints
```
GET    /api/v1/family-relationships                    # List relationships
POST   /api/v1/family-relationships                    # Create relationship
GET    /api/v1/family-relationships/{id}               # Get relationship
PUT    /api/v1/family-relationships/{id}               # Update relationship
DELETE /api/v1/family-relationships/{id}               # Delete relationship
GET    /api/v1/family-relationships/by-student/{id}    # Student relationships
GET    /api/v1/family-relationships/primary-contact/{id}
GET    /api/v1/family-relationships/emergency-contacts/{id}
GET    /api/v1/family-relationships/authorized-pickup/{id}
POST   /api/v1/family-relationships/set-primary-contact/{id}
POST   /api/v1/family-relationships/bulk-create        # Bulk create
GET    /api/v1/family-relationships/search-potential-members
GET    /api/v1/family-relationships/statistics
```

## 5. School Controller

**File**: `app/Http/Controllers/API/V1/School/SchoolController.php`

### Features
- School information management
- School dashboard and analytics
- Student enrollment tracking
- Academic year management
- Performance metrics

### Key Methods

#### Core Operations
- `index()` - List schools with filters
- `store()` - Create school with setup workflow
- `show()` - Display school details
- `update()` - Update school information
- `destroy()` - Remove school

#### Special Operations
- `getDashboard()` - School dashboard data
- `getStatistics()` - School statistics
- `getStudents()` - Get school students
- `getAcademicYears()` - Get school academic years
- `setCurrentAcademicYear()` - Set current year
- `getPerformanceMetrics()` - Performance analytics

#### Form Engine Integration
- Processes `school_registration` forms
- Creates form instances for tracking
- Starts `school_setup` workflow

#### Workflow Integration
- **School Setup Workflow**: 4-step process
  - Initial setup
  - Staff assignment
  - Curriculum setup
  - Final approval

### API Endpoints
```
GET    /api/v1/schools                    # List schools
POST   /api/v1/schools                    # Create school
GET    /api/v1/schools/{id}               # Get school
PUT    /api/v1/schools/{id}               # Update school
DELETE /api/v1/schools/{id}               # Delete school
GET    /api/v1/schools/{id}/dashboard     # School dashboard
GET    /api/v1/schools/{id}/statistics    # School statistics
GET    /api/v1/schools/{id}/students      # School students
GET    /api/v1/schools/{id}/academic-years
POST   /api/v1/schools/{id}/set-current-academic-year
GET    /api/v1/schools/{id}/performance-metrics
```

## 6. Academic Year Controller

**File**: `app/Http/Controllers/API/V1/School/AcademicYearController.php`

### Features
- Academic year planning and management
- Date overlap validation
- Holiday management
- Current year management
- Bulk year creation

### Key Methods

#### Core Operations
- `index()` - List academic years
- `store()` - Create academic year with workflow
- `show()` - Display year details
- `update()` - Update year information
- `destroy()` - Remove academic year

#### Special Operations
- `getBySchool()` - Get school's academic years
- `getCurrent()` - Get current academic year
- `setAsCurrent()` - Set as current year
- `getCalendar()` - Get year calendar
- `bulkCreate()` - Create multiple years

#### Analytics
- `getStatistics()` - Year statistics
- `getTrends()` - Time-based trends

#### Form Engine Integration
- Processes `academic_year_setup` forms
- Creates form instances for tracking
- Starts `academic_year_setup` workflow

#### Workflow Integration
- **Academic Year Setup Workflow**: 5-step process
  - Initial setup
  - Term planning
  - Curriculum setup
  - Staff assignment
  - Final approval

### API Endpoints
```
GET    /api/v1/academic-years                    # List academic years
POST   /api/v1/academic-years                    # Create academic year
GET    /api/v1/academic-years/{id}               # Get academic year
PUT    /api/v1/academic-years/{id}               # Update academic year
DELETE /api/v1/academic-years/{id}               # Delete academic year
GET    /api/v1/academic-years/by-school/{id}     # School years
GET    /api/v1/academic-years/current/{id}       # Current year
POST   /api/v1/academic-years/{id}/set-as-current
GET    /api/v1/academic-years/{id}/calendar      # Year calendar
POST   /api/v1/academic-years/bulk-create        # Bulk create
GET    /api/v1/academic-years/statistics         # Statistics
GET    /api/v1/academic-years/trends             # Trends
```

## 7. Academic Term Controller

**File**: `app/Http/Controllers/API/V1/School/AcademicTermController.php`

### Features
- Academic term management within years
- Term type management (semester, quarter, trimester)
- Date overlap validation
- Current term management
- Bulk term creation

### Key Methods

#### Core Operations
- `index()` - List academic terms
- `store()` - Create term with workflow
- `show()` - Display term details
- `update()` - Update term information
- `destroy()` - Remove academic term

#### Special Operations
- `getByAcademicYear()` - Get year's terms
- `getCurrent()` - Get current term
- `setAsCurrent()` - Set as current term
- `getCalendar()` - Get term calendar
- `bulkCreate()` - Create multiple terms

#### Analytics
- `getStatistics()` - Term statistics
- `getTrends()` - Term-based trends

#### Form Engine Integration
- Processes `academic_term_setup` forms
- Creates form instances for tracking
- Starts `term_setup` workflow

#### Workflow Integration
- **Term Setup Workflow**: 5-step process
  - Initial setup
  - Curriculum planning
  - Staff assignment
  - Schedule setup
  - Final approval

### API Endpoints
```
GET    /api/v1/academic-terms                    # List academic terms
POST   /api/v1/academic-terms                    # Create academic term
GET    /api/v1/academic-terms/{id}               # Get academic term
PUT    /api/v1/academic-terms/{id}               # Update academic term
DELETE /api/v1/academic-terms/{id}               # Delete academic term
GET    /api/v1/academic-terms/by-academic-year/{id}
GET    /api/v1/academic-terms/current/{id}       # Current term
POST   /api/v1/academic-terms/{id}/set-as-current
GET    /api/v1/academic-terms/{id}/calendar      # Term calendar
POST   /api/v1/academic-terms/bulk-create        # Bulk create
GET    /api/v1/academic-terms/statistics         # Statistics
GET    /api/v1/academic-terms/trends             # Trends
```

## Form Engine Integration

All controllers integrate with the Form Engine service to:

1. **Process Dynamic Forms**: Handle form data based on templates
2. **Create Form Instances**: Track form submissions for audit trails
3. **Validate Data**: Apply template-based validation rules
4. **AI Enhancement**: Apply AI-based field completion and validation

### Form Types Supported
- `student_registration` - Student enrollment forms
- `document_upload` - Document submission forms
- `student_enrollment` - Enrollment processing forms
- `family_relationship` - Family member forms
- `school_registration` - School setup forms
- `academic_year_setup` - Academic year planning forms
- `academic_term_setup` - Term planning forms

## Workflow Integration

All controllers integrate with the Workflow Service to:

1. **Automate Processes**: Define multi-step business processes
2. **Track Progress**: Monitor workflow completion status
3. **Assign Tasks**: Distribute work among staff members
4. **Ensure Compliance**: Enforce business rules and approvals

### Workflow Types
- **Student Enrollment**: 4-step enrollment process
- **Document Verification**: 4-step document review
- **Student Transfer**: 4-step transfer process
- **Relationship Verification**: 4-step family verification
- **School Setup**: 4-step school initialization
- **Academic Year Setup**: 5-step year planning
- **Term Setup**: 5-step term planning

## Security Features

### Authentication & Authorization
- JWT-based authentication
- Role-based access control
- Tenant isolation
- Permission-based operations

### Data Validation
- Comprehensive input validation
- Business rule enforcement
- Data integrity checks
- SQL injection prevention

### Audit Trail
- Form instance tracking
- Workflow progress logging
- User action logging
- Change history tracking

## Error Handling

### Standardized Responses
```json
{
    "success": true/false,
    "message": "Human readable message",
    "data": {...},
    "errors": {...}
}
```

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `422` - Validation error
- `404` - Not found
- `500` - Server error

### Exception Handling
- Database transaction rollback
- Graceful error messages
- Detailed logging
- User-friendly responses

## Performance Features

### Database Optimization
- Eager loading of relationships
- Pagination support
- Efficient queries
- Index optimization

### Caching Support
- Query result caching
- Configuration caching
- Session caching
- Response caching

### Bulk Operations
- Batch processing
- Transaction management
- Error handling
- Progress tracking

## Usage Examples

### Creating a Student with Enrollment
```php
$response = $this->post('/api/v1/students', [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@example.com',
    'school_id' => 1,
    'academic_year_id' => 1,
    'grade_level' => '10',
    'form_data' => [
        'emergency_contact' => 'Jane Doe',
        'medical_conditions' => 'None'
    ]
]);
```

### Uploading a Document
```php
$response = $this->post('/api/v1/student-documents', [
    'student_id' => 1,
    'document_type' => 'birth_certificate',
    'document_name' => 'Birth Certificate',
    'file_path' => 'students/1/documents/birth_cert.pdf',
    'file_size' => 1024000,
    'file_type' => 'application/pdf'
]);
```

### Setting Current Academic Year
```php
$response = $this->post('/api/v1/academic-years/1/set-as-current');
```

## Testing

### Unit Tests
- Controller method testing
- Service integration testing
- Workflow testing
- Form processing testing

### Feature Tests
- API endpoint testing
- Authentication testing
- Authorization testing
- Data validation testing

### Integration Tests
- Database operations
- File operations
- Workflow execution
- Form processing

## Future Enhancements

### Planned Features
- Real-time notifications
- Advanced analytics dashboard
- Mobile API optimization
- Bulk import/export
- Advanced search capabilities
- Report generation
- Integration with external systems

### Scalability Improvements
- Microservice architecture
- Event-driven processing
- Queue-based operations
- Distributed caching
- Load balancing support

## Support

For questions or issues with these controllers:

1. Check the Laravel logs
2. Review the workflow status
3. Verify form template configuration
4. Check database constraints
5. Review permission settings

## Contributing

When contributing to these controllers:

1. Follow Laravel coding standards
2. Add comprehensive tests
3. Update documentation
4. Follow the existing patterns
5. Ensure Form Engine integration
6. Include workflow definitions
