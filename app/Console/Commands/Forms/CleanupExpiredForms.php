<?php 

namespace App\Console\Commands\Forms;

use App\Models\Forms\FormInstance;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanupExpiredForms extends Command
{
    protected $signature = 'forms:cleanup-expired 
                            {--days=30 : Number of days after which draft forms are considered expired}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up expired draft form instances';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $expiredDate = Carbon::now()->subDays($days);
        
        $query = FormInstance::where('status', 'draft')
            ->where('updated_at', '<', $expiredDate)
            ->whereDoesntHave('submissions', function ($query) {
                $query->where('submission_type', '!=', 'auto_save');
            });
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No expired forms found.');
            return 0;
        }
        
        if ($dryRun) {
            $this->info("Would delete {$count} expired form instances (older than {$days} days)");
            
            $instances = $query->with('template:id,name')->limit(10)->get();
            $this->table(
                ['ID', 'Template', 'User ID', 'Last Updated'],
                $instances->map(function ($instance) {
                    return [
                        $instance->id,
                        $instance->template->name,
                        $instance->user_id,
                        $instance->updated_at->format('Y-m-d H:i:s')
                    ];
                })
            );
            
            if ($count > 10) {
                $this->info("... and " . ($count - 10) . " more");
            }
        } else {
            if ($this->confirm("Delete {$count} expired form instances?")) {
                $deleted = $query->delete();
                $this->info("Deleted {$deleted} expired form instances.");
            } else {
                $this->info('Operation cancelled.');
            }
        }
        
        return 0;
    }
}
