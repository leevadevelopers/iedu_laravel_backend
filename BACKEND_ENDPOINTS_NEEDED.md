# Backend Endpoints Needed for School Owner Module

## Missing Endpoints

The following endpoints are needed to fully support the School Owner module calendar functionality:

### 1. School Calendar Endpoint
**Endpoint**: `GET /v1/schools/{id}/calendar`

**Purpose**: Get unified calendar data (academic year + school events)

**Request Parameters**:
- `start_date` (optional): Start date for calendar range (YYYY-MM-DD)
- `end_date` (optional): End date for calendar range (YYYY-MM-DD)
- `event_type` (optional): Filter by event type

**Response Format**:
```json
{
  "success": true,
  "data": {
    "academic_year": {
      "id": "string",
      "name": "string",
      "start_date": "YYYY-MM-DD",
      "end_date": "YYYY-MM-DD",
      "terms": [
        {
          "id": "string",
          "name": "string",
          "start_date": "YYYY-MM-DD",
          "end_date": "YYYY-MM-DD"
        }
      ]
    },
    "events": [
      {
        "id": "string",
        "school_id": "string",
        "title": "string",
        "description": "string",
        "event_type": "academic|holiday|activity|meeting|exam|other",
        "start_date": "YYYY-MM-DDTHH:mm:ss",
        "end_date": "YYYY-MM-DDTHH:mm:ss",
        "all_day": boolean,
        "location": "string",
        "color": "string"
      }
    ],
    "holidays": []
  }
}
```

**Implementation Location**: `app/Http/Controllers/API/V1/School/SchoolController.php`

**Suggested Method**:
```php
public function getCalendar(Request $request, $id): JsonResponse
{
    // Get current academic year
    // Get school events in date range
    // Merge and return
}
```

### 2. School Events CRUD Endpoints
**Endpoints**:
- `GET /v1/schools/{id}/events` - List school events
- `POST /v1/schools/{id}/events` - Create school event
- `PUT /v1/schools/{id}/events/{eventId}` - Update school event
- `DELETE /v1/schools/{id}/events/{eventId}` - Delete school event

**Purpose**: Manage school-wide events (holidays, activities, meetings, etc.)

**Request Parameters for GET**:
- `start_date` (optional): Filter events from this date
- `end_date` (optional): Filter events until this date
- `event_type` (optional): Filter by event type

**Response Format for GET**:
```json
{
  "success": true,
  "data": {
    "events": [
      {
        "id": "string",
        "school_id": "string",
        "title": "string",
        "description": "string",
        "event_type": "academic|holiday|activity|meeting|exam|other",
        "start_date": "YYYY-MM-DDTHH:mm:ss",
        "end_date": "YYYY-MM-DDTHH:mm:ss",
        "all_day": boolean,
        "location": "string",
        "color": "string",
        "created_at": "timestamp",
        "updated_at": "timestamp"
      }
    ]
  }
}
```

**Request Body for POST/PUT**:
```json
{
  "title": "string (required)",
  "description": "string (optional)",
  "event_type": "academic|holiday|activity|meeting|exam|other (required)",
  "start_date": "YYYY-MM-DDTHH:mm:ss (required)",
  "end_date": "YYYY-MM-DDTHH:mm:ss (required)",
  "all_day": boolean (default: false),
  "location": "string (optional)",
  "color": "string (optional)"
}
```

**Implementation Location**: 
- Controller: `app/Http/Controllers/API/V1/School/SchoolEventController.php` (new)
- Or add methods to: `app/Http/Controllers/API/V1/School/SchoolController.php`

**Database Migration Needed**:
```php
Schema::create('school_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
    $table->string('title');
    $table->text('description')->nullable();
    $table->enum('event_type', ['academic', 'holiday', 'activity', 'meeting', 'exam', 'other']);
    $table->dateTime('start_date');
    $table->dateTime('end_date');
    $table->boolean('all_day')->default(false);
    $table->string('location')->nullable();
    $table->string('color')->nullable();
    $table->timestamps();
    
    $table->index(['school_id', 'start_date', 'end_date']);
});
```

## Existing Endpoints (Verified)

The following endpoints already exist and are being used:

- ✅ `GET /v1/schools/{id}` - Get school profile
- ✅ `PUT /v1/schools/{id}` - Update school profile
- ✅ `GET /v1/schools/{id}/settings` - Get school settings
- ✅ `PUT /v1/schools/{id}/settings` - Update school settings
- ✅ `GET /v1/academic-years` - List academic years
- ✅ `GET /v1/academic-years/{id}/calendar` - Get academic year calendar
- ✅ `GET /v1/schedules` - List schedules
- ✅ `GET /v1/school-owner/dashboard` - School owner dashboard

## Notes

1. The frontend components are designed to gracefully handle missing endpoints by:
   - Using fallback methods when unified calendar endpoint is not available
   - Showing empty states when events endpoint is not available
   - Providing clear error messages

2. The calendar component will work with existing academic year calendar endpoint, but will have limited functionality without the events endpoints.

3. All endpoints should respect:
   - Authentication middleware (`auth:api`)
   - Tenant middleware (`tenant`)
   - School owner authorization (user must own/manage the school)

