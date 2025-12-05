# Tenant Architecture Explanation

## What is a Tenant in This Application?

A **Tenant** in this iEDU application represents an **organization or company** that can own and manage multiple schools. Think of it as a "parent organization" or "education group."

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    TENANT (Organization)                     │
│  - Example: "Acme Education Group"                           │
│  - Can own multiple schools                                 │
│  - Has subscription/billing                                 │
│  - Has settings and features                                │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ (one-to-many)
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
   ┌────────┐    ┌────────┐    ┌────────┐
   │ School │    │ School │    │ School │
   │   A    │    │   B    │    │   C    │
   └────┬───┘    └────┬───┘    └────┬───┘
        │             │              │
        └──────────────┼──────────────┘
                       │
                       │ (many-to-many via school_users)
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
   ┌────────┐    ┌────────┐    ┌────────┐
   │  User  │    │  User  │    │  User  │
   │(Owner) │    │(Admin) │    │(Teacher)│
   └────────┘    └────────┘    └────────┘
```

## Key Relationships

### 1. Tenant → Schools (One-to-Many)
- **One tenant can have many schools**
- Each school belongs to exactly one tenant
- `schools.tenant_id` → `tenants.id`

**Example:**
- Tenant: "Acme Education Group"
  - School A: "Acme Primary School"
  - School B: "Acme High School"
  - School C: "Acme Technical Institute"

### 2. Tenant → Users (Many-to-Many via `tenant_users`)
- **Users can belong to multiple tenants**
- Each user-tenant relationship has:
  - `role_id`: Role within that tenant
  - `permissions`: Specific permissions
  - `current_tenant`: Which tenant is currently active
  - `status`: active/inactive/suspended
  - `joined_at`: When they joined

**Example:**
- User "John" can be:
  - School Owner in Tenant 1
  - Consultant in Tenant 2
  - Teacher in Tenant 3

### 3. User → Schools (Many-to-Many via `school_users`)
- **Users can belong to multiple schools**
- Each user-school relationship has:
  - `role`: 'owner', 'admin', 'teacher', 'staff'
  - `status`: 'active', 'inactive'
  - `start_date`, `end_date`: Validity period
  - `permissions`: School-specific permissions

**Example:**
- User "John" can be:
  - Owner of School A
  - Admin of School B
  - Teacher in School C

## Data Isolation & Scoping

### Tenant-Level Scoping
- Most models have `tenant_id` field
- Data is automatically scoped by tenant using `Tenantable` trait
- Users can only see data from their current tenant

### School-Level Scoping
- Many models have `school_id` field
- Data is automatically scoped by school using `HasSchoolScope` trait
- Users can only see data from their current school

### Scoping Hierarchy
```
Super Admin
  └─> Can see ALL tenants and ALL schools

Tenant Owner/Admin
  └─> Can see their tenant's data
      └─> Can see their tenant's schools

School Owner/Admin
  └─> Can see their school's data only
      └─> Scoped by school_id
```

## Real-World Example

### Scenario: "Acme Education Group"

**Tenant Setup:**
```
Tenant ID: 1
Name: "Acme Education Group"
Owner: John Doe (user_id: 2)
```

**Schools:**
```
School ID: 1
Name: "Acme Primary School"
Tenant ID: 1

School ID: 2
Name: "Acme High School"
Tenant ID: 1
```

**Users:**
```
User: John Doe (ID: 2)
├─ Tenant Association (tenant_users):
│  └─ Tenant 1, Role: 'school_owner', current_tenant: true
└─ School Association (school_users):
   ├─ School 1, Role: 'owner', status: 'active'
   └─ School 2, Role: 'owner', status: 'active'

User: Jane Smith (ID: 3)
├─ Tenant Association (tenant_users):
│  └─ Tenant 1, Role: 'teacher', current_tenant: false
└─ School Association (school_users):
   └─ School 1, Role: 'teacher', status: 'active'
```

## Why This Architecture?

### Benefits:
1. **Multi-Organization Support**: One platform can serve multiple education groups
2. **Flexible User Management**: Users can work across multiple tenants/schools
3. **Data Isolation**: Each tenant's data is completely separate
4. **Scalability**: Easy to add new tenants without affecting others
5. **Billing**: Each tenant can have its own subscription/billing

### Use Cases:
- **Education Management Company**: Manages multiple school chains
- **Franchise Model**: One organization, multiple school locations
- **Multi-School Districts**: One district, multiple schools
- **Consultants**: Work with multiple organizations

## Current Context Resolution

When a user logs in, the system determines context in this order:

1. **Tenant Context** (from `tenant_users`):
   - Check session `tenant_id`
   - Check `X-Tenant-ID` header
   - Check `current_tenant = true` in `tenant_users`
   - Fallback to first tenant

2. **School Context** (from `school_users`):
   - Check session `current_school_id`
   - Check user's `schools()` relationship
   - **NEW**: Auto-associate with tenant's schools if needed
   - Fallback to first school

## Important Notes

1. **Tenant is NOT the same as School**
   - Tenant = Organization/Company
   - School = Individual educational institution

2. **Users belong to BOTH**
   - `tenant_users`: Which organizations they work for
   - `school_users`: Which specific schools they work at

3. **Auto-Association Feature**
   - If a user belongs to a tenant but not to schools
   - System can auto-associate them with tenant's schools
   - Role mapping: `school_owner` → `owner`, `school_admin` → `admin`, etc.

4. **Super Admin Exception**
   - Super admins bypass tenant/school scoping
   - They can see all data across all tenants

## Database Tables Summary

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `tenants` | Organizations/Companies | id, name, slug, domain, owner_id |
| `tenant_users` | User-Tenant relationships | tenant_id, user_id, role_id, current_tenant, status |
| `schools` | Educational institutions | id, tenant_id, school_code, official_name |
| `school_users` | User-School relationships | school_id, user_id, role, status, start_date, end_date |

## Migration Path

If you're migrating from a single-tenant system:
1. Create a default tenant (ID: 1)
2. Associate all existing schools with that tenant
3. Associate all users with that tenant via `tenant_users`
4. Associate users with schools via `school_users`

This maintains backward compatibility while enabling multi-tenancy.

