<?php

namespace App\Jobs\Transport;

use App\Models\V1\SIS\School\School;
use App\Models\V1\Transport\TransportRoute;
use App\Services\V1\Transport\TransportRouteService;
use App\Events\V1\Transport\RouteOptimized;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OptimizeTransportRoutes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes for complex optimization
    public $tries = 1; // Don't retry optimization jobs

    protected $schoolId;
    protected $routeIds;

    public function __construct(int $schoolId, array $routeIds = [])
    {
        $this->schoolId = $schoolId;
        $this->routeIds = $routeIds;
    }

    public function handle(TransportRouteService $routeService)
    {
        try {
            Log::info('Starting route optimization', [
                'school_id' => $this->schoolId,
                'route_ids' => $this->routeIds
            ]);

            $school = School::findOrFail($this->schoolId);

            // Get routes to optimize
            $routes = $this->getRoutesToOptimize();

            $totalSavings = 0;
            $optimizedRoutes = [];

            foreach ($routes as $route) {
                try {
                    $results = $routeService->optimizeRoute($route);

                    $optimizedRoutes[] = [
                        'route_id' => $route->id,
                        'route_name' => $route->name,
                        'results' => $results
                    ];

                    $totalSavings += $results['distance_saved'];

                    // Fire optimization event
                    event(new RouteOptimized($route, $results));

                } catch (\Exception $e) {
                    Log::error('Failed to optimize route', [
                        'route_id' => $route->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Route optimization completed', [
                'school_id' => $this->schoolId,
                'routes_optimized' => count($optimizedRoutes),
                'total_distance_saved' => $totalSavings
            ]);

        } catch (\Exception $e) {
            Log::error('Route optimization job failed', [
                'school_id' => $this->schoolId,
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function getRoutesToOptimize()
    {
        $query = TransportRoute::where('school_id', $this->schoolId)
            ->where('status', 'active')
            ->with(['busStops']);

        if (!empty($this->routeIds)) {
            $query->whereIn('id', $this->routeIds);
        }

        return $query->get();
    }

    public function failed(\Throwable $exception)
    {
        Log::error('OptimizeTransportRoutes job failed permanently', [
            'school_id' => $this->schoolId,
            'route_ids' => $this->routeIds,
            'exception' => $exception->getMessage()
        ]);
    }
}
