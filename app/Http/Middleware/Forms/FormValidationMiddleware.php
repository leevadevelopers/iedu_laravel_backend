<? 
namespace App\Http\Middleware\Forms;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class FormValidationMiddleware
{
    /**
     * Validate form data structure
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only validate for form-related endpoints
        if (!$this->shouldValidate($request)) {
            return $next($request);
        }

        // Validate form data size
        $maxSize = $this->parseSize(config('form_engine.defaults.max_form_data_size', '50MB'));
        $contentLength = $request->header('Content-Length', 0);
        
        if ($contentLength > $maxSize) {
            return response()->json([
                'message' => 'Form data exceeds maximum allowed size',
                'max_size' => config('form_engine.defaults.max_form_data_size')
            ], 413);
        }

        // Validate form data structure if present
        if ($request->has('form_data') && is_array($request->form_data)) {
            $validation = $this->validateFormDataStructure($request->form_data);
            
            if (!$validation['valid']) {
                return response()->json([
                    'message' => 'Invalid form data structure',
                    'errors' => $validation['errors']
                ], 422);
            }
        }

        return $next($request);
    }

    private function shouldValidate(Request $request): bool
    {
        $formEndpoints = [
            'form-instances.store',
            'form-instances.update',
            'form-instances.submit',
            'form-instances.auto-save'
        ];

        return in_array($request->route()?->getName(), $formEndpoints);
    }

    private function parseSize(string $size): int
    {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1048576, 'GB' => 1073741824];
        
        if (preg_match('/(\d+)\s*([A-Z]{1,2})/i', $size, $matches)) {
            $value = (int) $matches[1];
            $unit = strtoupper($matches[2]);
            return $value * ($units[$unit] ?? 1);
        }
        
        return (int) $size;
    }

    private function validateFormDataStructure(array $formData): array
    {
        $errors = [];
        $maxDepth = 10;
        
        // Check for circular references and excessive nesting
        try {
            $this->checkDataStructure($formData, 0, $maxDepth);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function checkDataStructure($data, int $currentDepth, int $maxDepth): void
    {
        if ($currentDepth > $maxDepth) {
            throw new \Exception('Form data structure exceeds maximum nesting depth');
        }
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $this->checkDataStructure($value, $currentDepth + 1, $maxDepth);
                }
            }
        }
    }
}