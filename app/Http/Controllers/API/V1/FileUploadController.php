<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    /**
     * Upload a file
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'folder' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500'
        ]);

        try {
            $file = $request->file('file');
            $folder = $request->input('folder', 'uploads');
            $description = $request->input('description');

            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;

            // Store file
            $path = $file->storeAs($folder, $filename, 'public');

            // Get file info
            $fileInfo = [
                'id' => Str::uuid(),
                'original_name' => $originalName,
                'filename' => $filename,
                'path' => $path,
                'url' => Storage::url($path),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $extension,
                'folder' => $folder,
                'description' => $description,
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now()->toISOString()
            ];

            Log::info('File uploaded successfully', [
                'user_id' => auth()->id(),
                'filename' => $originalName,
                'path' => $path
            ]);

            return response()->json([
                'data' => $fileInfo,
                'message' => 'File uploaded successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to upload file',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'files.*' => 'required|file|max:10240', // 10MB max per file
            'folder' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500'
        ]);

        try {
            $files = $request->file('files');
            $folder = $request->input('folder', 'uploads');
            $description = $request->input('description');

            $uploadedFiles = [];

            foreach ($files as $file) {
                // Generate unique filename
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $filename = Str::uuid() . '.' . $extension;

                // Store file
                $path = $file->storeAs($folder, $filename, 'public');

                $fileInfo = [
                    'id' => Str::uuid(),
                    'original_name' => $originalName,
                    'filename' => $filename,
                    'path' => $path,
                    'url' => Storage::url($path),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $extension,
                    'folder' => $folder,
                    'description' => $description,
                    'uploaded_by' => auth()->id(),
                    'uploaded_at' => now()->toISOString()
                ];

                $uploadedFiles[] = $fileInfo;
            }

            Log::info('Multiple files uploaded successfully', [
                'user_id' => auth()->id(),
                'count' => count($uploadedFiles)
            ]);

            return response()->json([
                'data' => $uploadedFiles,
                'message' => count($uploadedFiles) . ' files uploaded successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Multiple file upload failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to upload files',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a file
     */
    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        try {
            $path = $request->input('path');

            // Check if file exists
            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'error' => 'File not found'
                ], 404);
            }

            // Delete file
            Storage::disk('public')->delete($path);

            Log::info('File deleted successfully', [
                'user_id' => auth()->id(),
                'path' => $path
            ]);

            return response()->json([
                'message' => 'File deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to delete file',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file info
     */
    public function info(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        try {
            $path = $request->input('path');

            // Check if file exists
            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'error' => 'File not found'
                ], 404);
            }

            $fileInfo = [
                'path' => $path,
                'url' => Storage::url($path),
                'size' => Storage::disk('public')->size($path),
                'last_modified' => Storage::disk('public')->lastModified($path),
                'mime_type' => Storage::disk('public')->mimeType($path)
            ];

            return response()->json([
                'data' => $fileInfo
            ]);

        } catch (\Exception $e) {
            Log::error('File info retrieval failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get file info',
                'message' => $e->getMessage()
            ], 500);
        }
    }
} 