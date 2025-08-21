<?php
// File: app/Services/Forms/MethodologyAdapterService.php
namespace App\Services\Forms;

use App\Services\Forms\Methodology\USAIDMethodologyAdapter;
use App\Services\Forms\Methodology\WorldBankMethodologyAdapter;
use App\Services\Forms\Methodology\EUMethodologyAdapter;

use Illuminate\Support\Facades\Log;

class MethodologyAdapterService
{
    private $adapters = [];

    public function __construct()
    {
        $this->adapters = [
            'usaid' => new USAIDMethodologyAdapter(),
            'world_bank' => new WorldBankMethodologyAdapter(),
            'eu' => new EUMethodologyAdapter(),
        ];
    }

    /**
     * Adapt template to specific methodology
     */
    public function adaptTemplate(array $templateData, string $methodology): array
    {
        $adapter = $this->adapters[$methodology] ?? null;
        
        if (!$adapter) {
            Log::warning("No adapter found for methodology: {$methodology}");
            return $templateData;
        }
        
        try {
            return $adapter->adaptTemplate($templateData);
        } catch (\Exception $e) {
            Log::error("Failed to adapt template for methodology {$methodology}", [
                'error' => $e->getMessage(),
                'template_name' => $templateData['name'] ?? 'Unknown'
            ]);
            return $templateData;
        }
    }

    /**
     * Get methodology requirements
     */
    public function getMethodologyRequirements(string $methodology): array
    {
        $adapter = $this->adapters[$methodology] ?? null;
        
        if (!$adapter) {
            return [];
        }
        
        return $adapter->getRequirements();
    }

    /**
     * Get compliance configuration for methodology
     */
    public function getComplianceConfiguration(string $methodology): array
    {
        $adapter = $this->adapters[$methodology] ?? null;
        
        if (!$adapter) {
            return [];
        }
        
        return $adapter->getComplianceConfiguration();
    }
}
