<?php

namespace App\Http\Controllers\API\V1\SuperAdmin;

use App\Http\Controllers\API\V1\BaseController;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use App\Models\Settings\Tenant;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\Payment;
use App\Models\V1\Financial\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SuperAdminReportsController extends BaseController
{
    /**
     * Get school reports
     */
    public function schools(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'school_type', 'state_province', 'country_code', 'date_from', 'date_to', 'search']);
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $query = School::withoutGlobalScopes();

            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['school_type'])) {
                $query->where('school_type', $filters['school_type']);
            }

            if (isset($filters['state_province'])) {
                $query->where('state_province', $filters['state_province']);
            }

            if (isset($filters['country_code'])) {
                $query->where('country_code', $filters['country_code']);
            }

            if (isset($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('official_name', 'like', "%{$search}%")
                      ->orWhere('display_name', 'like', "%{$search}%")
                      ->orWhere('school_code', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Get statistics
            $totalSchools = School::withoutGlobalScopes()->count();
            $activeSchools = School::withoutGlobalScopes()->where('status', 'active')->count();
            $inactiveSchools = School::withoutGlobalScopes()->where('status', 'inactive')->count();
            $suspendedSchools = School::withoutGlobalScopes()->where('status', 'suspended')->count();

            $schoolsByType = School::withoutGlobalScopes()
                ->select('school_type', DB::raw('count(*) as count'))
                ->groupBy('school_type')
                ->get()
                ->pluck('count', 'school_type');

            $schoolsByRegion = School::withoutGlobalScopes()
                ->select('state_province', DB::raw('count(*) as count'))
                ->whereNotNull('state_province')
                ->groupBy('state_province')
                ->get()
                ->pluck('count', 'state_province');

            // Get paginated schools
            $schools = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->successResponse([
                'statistics' => [
                    'total' => $totalSchools,
                    'active' => $activeSchools,
                    'inactive' => $inactiveSchools,
                    'suspended' => $suspendedSchools,
                    'by_type' => $schoolsByType,
                    'by_region' => $schoolsByRegion,
                ],
                'data' => $schools->items(),
                'meta' => [
                    'current_page' => $schools->currentPage(),
                    'last_page' => $schools->lastPage(),
                    'per_page' => $schools->perPage(),
                    'total' => $schools->total(),
                ],
            ], 'School reports retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve school reports: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export school reports
     */
    public function exportSchools(Request $request)
    {
        try {
            $filters = $request->only(['status', 'school_type', 'state_province', 'country_code', 'date_from', 'date_to', 'search']);

            $query = School::withoutGlobalScopes();

            // Apply same filters as schools method
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (isset($filters['school_type'])) {
                $query->where('school_type', $filters['school_type']);
            }
            if (isset($filters['state_province'])) {
                $query->where('state_province', $filters['state_province']);
            }
            if (isset($filters['country_code'])) {
                $query->where('country_code', $filters['country_code']);
            }
            if (isset($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }
            if (isset($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }
            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('official_name', 'like', "%{$search}%")
                      ->orWhere('display_name', 'like', "%{$search}%")
                      ->orWhere('school_code', 'like', "%{$search}%");
                });
            }

            $schools = $query->orderBy('created_at', 'desc')->get();

            // Generate CSV
            $filename = 'schools_report_' . date('Y-m-d_His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($schools) {
                $file = fopen('php://output', 'w');
                
                // Headers
                fputcsv($file, [
                    'ID', 'School Code', 'Official Name', 'Display Name', 'Type', 
                    'Status', 'Email', 'Phone', 'City', 'State', 'Country',
                    'Created At', 'Updated At'
                ]);

                // Data
                foreach ($schools as $school) {
                    fputcsv($file, [
                        $school->id,
                        $school->school_code,
                        $school->official_name,
                        $school->display_name,
                        $school->school_type,
                        $school->status,
                        $school->email,
                        $school->phone ?? '',
                        $school->city ?? '',
                        $school->state_province ?? '',
                        $school->country_code ?? '',
                        $school->created_at,
                        $school->updated_at,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to export school reports: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user reports
     */
    public function users(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['role', 'status', 'tenant_id', 'date_from', 'date_to', 'search']);
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $query = User::with(['roles']);

            // Apply filters
            if (isset($filters['role'])) {
                $query->whereHas('roles', function ($q) use ($filters) {
                    $q->where('name', $filters['role']);
                });
            }

            if (isset($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $query->where('is_active', true);
                } elseif ($filters['status'] === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            if (isset($filters['tenant_id'])) {
                $query->where('tenant_id', $filters['tenant_id']);
            }

            if (isset($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('identifier', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Get statistics
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $inactiveUsers = User::where('is_active', false)->count();

            $usersByRole = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('roles.name', DB::raw('count(*) as count'))
                ->groupBy('roles.name')
                ->get()
                ->pluck('count', 'name');

            $usersByTenant = User::select('tenant_id', DB::raw('count(*) as count'))
                ->whereNotNull('tenant_id')
                ->groupBy('tenant_id')
                ->get()
                ->map(function ($item) {
                    $tenant = \App\Models\Settings\Tenant::find($item->tenant_id);
                    return [
                        'tenant_id' => $item->tenant_id,
                        'tenant_name' => $tenant->name ?? 'N/A',
                        'count' => $item->count,
                    ];
                });

            // Get paginated users
            $users = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Add tenant information to each user
            $usersData = $users->items();
            foreach ($usersData as $user) {
                if ($user->tenant_id) {
                    $tenant = \App\Models\Settings\Tenant::find($user->tenant_id);
                    $user->tenant = $tenant ? ['id' => $tenant->id, 'name' => $tenant->name] : null;
                } else {
                    $user->tenant = null;
                }
            }

            return $this->successResponse([
                'statistics' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'inactive' => $inactiveUsers,
                    'by_role' => $usersByRole,
                    'by_tenant' => $usersByTenant,
                ],
                'data' => $usersData,
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ], 'User reports retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user reports: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export user reports
     */
    public function exportUsers(Request $request)
    {
        try {
            $filters = $request->only(['role', 'status', 'tenant_id', 'date_from', 'date_to', 'search']);

            $query = User::with(['roles']);

            // Apply same filters as users method
            if (isset($filters['role'])) {
                $query->whereHas('roles', function ($q) use ($filters) {
                    $q->where('name', $filters['role']);
                });
            }
            if (isset($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $query->where('is_active', true);
                } elseif ($filters['status'] === 'inactive') {
                    $query->where('is_active', false);
                }
            }
            if (isset($filters['tenant_id'])) {
                $query->where('tenant_id', $filters['tenant_id']);
            }
            if (isset($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }
            if (isset($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }
            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('identifier', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy('created_at', 'desc')->get();

            // Generate CSV
            $filename = 'users_report_' . date('Y-m-d_His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($users) {
                $file = fopen('php://output', 'w');
                
                // Headers
                fputcsv($file, [
                    'ID', 'Name', 'Identifier', 'Email', 'Phone', 
                    'Roles', 'Status', 'Tenant', 'Created At', 'Last Login'
                ]);

                // Data
                foreach ($users as $user) {
                    $roles = $user->roles->pluck('name')->join(', ');
                    $tenant = $user->tenant_id ? \App\Models\Settings\Tenant::find($user->tenant_id) : null;
                    fputcsv($file, [
                        $user->id,
                        $user->name,
                        $user->identifier ?? '',
                        $user->email ?? '',
                        $user->phone ?? '',
                        $roles,
                        $user->is_active ? 'Active' : 'Inactive',
                        $tenant->name ?? 'N/A',
                        $user->created_at,
                        $user->last_login_at ?? 'Never',
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to export user reports: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get financial reports
     */
    public function financial(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('date_from', now()->startOfMonth());
            $endDate = $request->get('date_to', now()->endOfMonth());

            // Total revenue across all tenants
            $totalRevenue = Invoice::withoutGlobalScopes()
                ->whereBetween('issued_at', [$startDate, $endDate])
                ->where('status', 'paid')
                ->sum('total');

            $totalPending = Invoice::withoutGlobalScopes()
                ->whereBetween('issued_at', [$startDate, $endDate])
                ->whereIn('status', ['issued', 'partially_paid'])
                ->sum('total');

            $totalOverdue = Invoice::withoutGlobalScopes()
                ->where('status', 'overdue')
                ->sum('total');

            $totalExpenses = Expense::withoutGlobalScopes()
                ->whereBetween('incurred_at', [$startDate, $endDate])
                ->sum('amount');

            $totalPayments = Payment::withoutGlobalScopes()
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->sum('amount');

            // Revenue by tenant
            $revenueByTenant = Invoice::withoutGlobalScopes()
                ->whereBetween('issued_at', [$startDate, $endDate])
                ->where('status', 'paid')
                ->select('tenant_id', DB::raw('SUM(total) as total'))
                ->groupBy('tenant_id')
                ->get()
                ->map(function ($item) {
                    $tenant = \App\Models\Settings\Tenant::find($item->tenant_id);
                    return [
                        'tenant_id' => $item->tenant_id,
                        'tenant_name' => $tenant->name ?? 'N/A',
                        'total' => (float) $item->total,
                    ];
                });

            // Expenses by category
            $expensesByCategory = Expense::withoutGlobalScopes()
                ->whereBetween('incurred_at', [$startDate, $endDate])
                ->select('category', DB::raw('SUM(amount) as total'))
                ->groupBy('category')
                ->get()
                ->pluck('total', 'category');

            return $this->successResponse([
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'summary' => [
                    'revenue' => [
                        'total' => (float) $totalRevenue,
                        'pending' => (float) $totalPending,
                        'overdue' => (float) $totalOverdue,
                    ],
                    'expenses' => [
                        'total' => (float) $totalExpenses,
                    ],
                    'payments' => [
                        'total' => (float) $totalPayments,
                    ],
                    'net_income' => (float) ($totalRevenue - $totalExpenses),
                ],
                'revenue_by_tenant' => $revenueByTenant,
                'expenses_by_category' => $expensesByCategory,
            ], 'Financial reports retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve financial reports: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export financial reports
     */
    public function exportFinancial(Request $request)
    {
        try {
            $startDate = $request->get('date_from', now()->startOfMonth());
            $endDate = $request->get('date_to', now()->endOfMonth());

            $invoices = Invoice::withoutGlobalScopes()
                ->whereBetween('issued_at', [$startDate, $endDate])
                ->orderBy('issued_at', 'desc')
                ->get();

            $expenses = Expense::withoutGlobalScopes()
                ->whereBetween('incurred_at', [$startDate, $endDate])
                ->orderBy('incurred_at', 'desc')
                ->get();

            // Generate CSV
            $filename = 'financial_report_' . date('Y-m-d_His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($invoices, $expenses) {
                $file = fopen('php://output', 'w');
                
                // Headers
                fputcsv($file, ['Type', 'Reference', 'Tenant', 'Amount', 'Status', 'Date']);

                // Invoices
                foreach ($invoices as $invoice) {
                    $tenant = \App\Models\Settings\Tenant::find($invoice->tenant_id);
                    fputcsv($file, [
                        'Invoice',
                        $invoice->reference ?? $invoice->id,
                        $tenant->name ?? 'N/A',
                        $invoice->total,
                        $invoice->status,
                        $invoice->issued_at,
                    ]);
                }

                // Expenses
                foreach ($expenses as $expense) {
                    $tenant = \App\Models\Settings\Tenant::find($expense->tenant_id);
                    fputcsv($file, [
                        'Expense',
                        $expense->id,
                        $tenant->name ?? 'N/A',
                        $expense->amount,
                        'Completed',
                        $expense->incurred_at,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to export financial reports: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get system performance metrics
     */
    public function systemPerformance(Request $request): JsonResponse
    {
        try {
            // Get active users count
            $activeUsers = User::where('is_active', true)->count();
            $totalUsers = User::count();

            // Get active schools count
            $activeSchools = School::withoutGlobalScopes()->where('status', 'active')->count();
            $totalSchools = School::withoutGlobalScopes()->count();

            // Get active tenants count
            $activeTenants = Tenant::count();

            // Get recent activity (last 24 hours)
            $recentUsers = User::where('created_at', '>=', now()->subDay())->count();
            $recentSchools = School::withoutGlobalScopes()->where('created_at', '>=', now()->subDay())->count();

            // Get database size (approximate)
            $databaseSize = DB::select("SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()")[0]->size_mb ?? 0;

            // Get cache statistics
            $cacheKeys = Cache::get('schools_cache_keys', []);
            $cacheSize = count($cacheKeys);

            // Get recent logins (last 7 days)
            $recentLogins = User::where('last_login_at', '>=', now()->subDays(7))->count();

            return $this->successResponse([
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'recent_24h' => $recentUsers,
                ],
                'schools' => [
                    'total' => $totalSchools,
                    'active' => $activeSchools,
                    'recent_24h' => $recentSchools,
                ],
                'tenants' => [
                    'total' => $activeTenants,
                ],
                'system' => [
                    'database_size_mb' => (float) $databaseSize,
                    'cache_keys' => $cacheSize,
                    'recent_logins_7d' => $recentLogins,
                ],
                'timestamp' => now()->toIso8601String(),
            ], 'System performance metrics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve system performance metrics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export data based on configuration
     */
    public function exportData(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'data_types' => 'required|array',
                'data_types.*' => 'in:schools,users,invoices,payments,expenses',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'format' => 'nullable|in:csv,excel,json',
            ]);

            $dataTypes = $request->get('data_types', []);
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $format = $request->get('format', 'csv');

            $exportData = [];

            foreach ($dataTypes as $type) {
                switch ($type) {
                    case 'schools':
                        $query = School::withoutGlobalScopes();
                        if ($dateFrom) $query->where('created_at', '>=', $dateFrom);
                        if ($dateTo) $query->where('created_at', '<=', $dateTo);
                        $exportData['schools'] = $query->get();
                        break;

                    case 'users':
                        $query = User::query();
                        if ($dateFrom) $query->where('created_at', '>=', $dateFrom);
                        if ($dateTo) $query->where('created_at', '<=', $dateTo);
                        $exportData['users'] = $query->get();
                        break;

                    case 'invoices':
                        $query = Invoice::withoutGlobalScopes();
                        if ($dateFrom) $query->where('issued_at', '>=', $dateFrom);
                        if ($dateTo) $query->where('issued_at', '<=', $dateTo);
                        $exportData['invoices'] = $query->get();
                        break;

                    case 'payments':
                        $query = Payment::withoutGlobalScopes();
                        if ($dateFrom) $query->where('paid_at', '>=', $dateFrom);
                        if ($dateTo) $query->where('paid_at', '<=', $dateTo);
                        $exportData['payments'] = $query->get();
                        break;

                    case 'expenses':
                        $query = Expense::withoutGlobalScopes();
                        if ($dateFrom) $query->where('incurred_at', '>=', $dateFrom);
                        if ($dateTo) $query->where('incurred_at', '<=', $dateTo);
                        $exportData['expenses'] = $query->get();
                        break;
                }
            }

            if ($format === 'json') {
                return response()->json([
                    'success' => true,
                    'data' => $exportData,
                ]);
            }

            // For CSV/Excel, return a simple message (actual file generation would be more complex)
            return $this->successResponse([
                'message' => 'Export data prepared',
                'data_types' => $dataTypes,
                'format' => $format,
                'record_counts' => array_map(fn($data) => count($data), $exportData),
            ], 'Export data prepared successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to export data: ' . $e->getMessage(), 500);
        }
    }
}

