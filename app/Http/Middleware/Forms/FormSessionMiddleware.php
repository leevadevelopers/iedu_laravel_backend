<?php 
namespace App\Http\Middleware\Forms;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FormSessionMiddleware
{
    /**
     * Handle form session management
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set form session timeout
        $timeout = config('form_engine.defaults.form_timeout', 3600);
        
        if ($request->hasSession()) {
            $request->session()->put('form_session_timeout', now()->addSeconds($timeout));
            
            // Check if form session has expired
            $lastActivity = $request->session()->get('form_last_activity');
            if ($lastActivity && now()->diffInSeconds($lastActivity) > $timeout) {
                $request->session()->forget(['form_data', 'form_instance_id']);
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Form session expired',
                        'code' => 'FORM_SESSION_EXPIRED'
                    ], 419);
                }
            }
            
            $request->session()->put('form_last_activity', now());
        }

        return $next($request);
    }
}