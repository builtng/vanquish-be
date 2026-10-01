<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SharedDocument;
use App\Models\Folder;
use App\Models\FolderShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SharedDocumentController extends Controller
{
    /**
     * List folders and files for a given parent folder.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $folderId = $request->get('folder_id'); // null for root

        $isStaff = in_array($user->role, ['super_admin', 'admin', 'staff', 'consultation_staff', 'compliance_officer']);
        
        // Initial query for folders
        $folderQuery = Folder::where('parent_id', $folderId)
            ->where('is_active', true);
            
        // Initial query for documents
        $documentQuery = SharedDocument::where('folder_id', $folderId)
            ->where('is_active', true);

        if (!$isStaff) {
            // Non-staff can only see shared content
            if ($folderId === null) {
                // At the root, only show shared folders
                $folderQuery->where('type', 'shared')
                    ->whereHas('shares', function($q) use ($user) {
                        $q->where('target_type', 'all_trainees')
                          ->orWhere('target_type', 'all_qualified')
                          ->orWhere(function($subq) use ($user) {
                              $subq->where('target_type', 'individual_user')
                                   ->where('target_id', $user->id);
                          });
                    });
                
                // Root files - show files uploaded by the user themselves even at root
                $documentQuery->where(function($q) use ($user) {
                    $q->whereRaw('1 = 0') // Default: hide root files to keep it organized
                      ->orWhere('uploaded_by', $user->id); // But show their own uploads
                });
            } else {
                // Inside a folder, check if the folder itself (or a parent) is shared
                // For simplicity, we assume root-level sharing access
                $currentFolder = Folder::find($folderId);
                if (!$currentFolder || $currentFolder->type === 'internal') {
                    return response()->json(['folders' => [], 'files' => []]);
                }
                
                // You could check permissions recursively here, but for now we trust the client logic 
                // and the fact that you can only "get" here by navigating from a shared root.
            }
        }

        $folders = $folderQuery->with(['creator:id,name', 'shares'])->orderBy('name')->get();
        $documents = $documentQuery->with('uploader:id,name')->orderBy('is_pinned', 'desc')->orderBy('created_at', 'desc')->get();
            
        return response()->json([
            'folders' => $folders,
            'files' => $documents,
            'current_folder' => $folderId ? Folder::with(['parent', 'shares'])->find($folderId) : null
        ]);
    }

    /**
     * Create a new folder.
     */
    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
            'type' => 'required|in:internal,shared',
            'client_id' => 'nullable|exists:clients,id',
            'tc_id' => 'nullable|exists:training_counsellors,id',
            'category' => 'nullable|in:private,shared,uploaded'
        ]);

        $folder = Folder::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
            'type' => $request->type,
            'client_id' => $request->client_id,
            'tc_id' => $request->tc_id,
            'category' => $request->category,
            'created_by' => $request->user()->id
        ]);

        return response()->json($folder, 201);
    }

    /**
     * Share a folder with specific targets.
     */
    public function shareFolder(Request $request, Folder $folder)
    {
        $request->validate([
            'shares' => 'required|array',
            'shares.*.target_type' => 'required|in:all_trainees,all_qualified,individual_client,individual_user',
            'shares.*.target_id' => 'nullable|integer'
        ]);

        DB::transaction(function () use ($request, $folder) {
            $folder->shares()->delete();
            foreach ($request->shares as $shareData) {
                $folder->shares()->create([
                    'target_type' => $shareData['target_type'],
                    'target_id' => $shareData['target_id']
                ]);
            }
            $folder->update(['type' => 'shared']);
        });

        return response()->json(['message' => 'Folder sharing updated']);
    }

    /**
     * Upload a file into a folder.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|max:10240', // 10MB limit
            'folder_id' => 'nullable|exists:folders,id',
            'client_id' => 'nullable|exists:clients,id',
            'tc_id' => 'nullable|exists:training_counsellors,id',
            'category' => 'nullable|in:private,shared,uploaded',
            'is_pinned' => 'nullable|boolean'
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileSize = $this->formatBytes($file->getSize());
        $fileType = strtoupper($file->getClientOriginalExtension());
        
        $path = $file->store('shared_documents', 'public');

        $folderId = $request->folder_id ?: null;
        $clientId = $request->client_id ?: null;
        $tcId = $request->tc_id ?: null;
        $category = $request->category ?: null;

        $user = $request->user();
        // If uploader is a counsellor and no TC association sent, auto-associate with them
        if ($user->role === 'counsellor' && !$tcId && $user->training_counsellor_id) {
            $tcId = $user->training_counsellor_id;
            // Also default to 'uploaded' category if no category or folder is provided
            if (!$category && !$folderId) $category = 'uploaded';
        }

        $document = SharedDocument::create([
            'folder_id' => $folderId,
            'client_id' => $clientId,
            'tc_id' => $tcId,
            'category' => $category,
            'name' => $request->name ?: $originalName,
            'description' => $request->description,
            'file_path' => $path,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'uploaded_by' => $user->id,
            'is_pinned' => $request->is_pinned ?: false
        ]);

        return response()->json($document, 201);
    }

    /**
     * Delete a file.
     */
    public function destroy(SharedDocument $document)
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();
        
        return response()->json(['message' => 'Document deleted successfully']);
    }

    /**
     * Delete a folder.
     */
    public function destroyFolder(Folder $folder)
    {
        $this->deleteFolderContents($folder);
        $folder->delete();

        return response()->json(['message' => 'Folder deleted successfully']);
    }

    private function deleteFolderContents(Folder $folder)
    {
        // Delete files in this folder
        foreach ($folder->documents as $doc) {
            Storage::disk('public')->delete($doc->file_path);
            $doc->delete();
        }

        // Delete subfolders
        foreach ($folder->children as $subfolder) {
            $this->deleteFolderContents($subfolder);
            $subfolder->delete();
        }
    }

    /**
     * Download the file.
     */
    public function download(SharedDocument $document)
    {
        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->name . '.' . pathinfo($document->file_path, PATHINFO_EXTENSION));
    }

    /**
     * List folders and files for a specific contact (Client or TC).
     */
    public function contactFiles(Request $request, $type, $id)
    {
        $folderId = $request->get('folder_id'); // null for root
        
        $contactField = $type === 'client' ? 'client_id' : 'tc_id';
        $contactModel = $type === 'client' ? \App\Models\Client::class : \App\Models\TrainingCounsellor::class;
        $contact = $contactModel::where('uuid', $id)->orWhere('id', $id)->firstOrFail();

        if (!$folderId) {
            // Virtual Root - return the 3 main categories as "folders"
            $categories = [
                [
                    'id' => 'private',
                    'name' => 'Private Files',
                    'description' => 'Contact cannot see files in this folder',
                    'type' => 'internal',
                    'category' => 'private',
                    'is_virtual' => true
                ],
                [
                    'id' => 'shared',
                    'name' => 'Shared with Contact',
                    'description' => 'Files shared with this Contact',
                    'type' => 'shared',
                    'category' => 'shared',
                    'is_virtual' => true
                ],
                [
                    'id' => 'uploaded',
                    'name' => 'Uploaded by Contact',
                    'description' => 'Contact can upload files in this folder',
                    'type' => 'shared',
                    'category' => 'uploaded',
                    'is_virtual' => true
                ]
            ];
            
            return response()->json([
                'folders' => $categories,
                'files' => [],
                'contact' => [
                    'id' => $contact->id,
                    'uuid' => $contact->uuid,
                    'name' => $contact->name,
                    'type' => $type
                ]
            ]);
        }

        // If folderId is one of the category names, list files in that category at the "root"
        if (in_array($folderId, ['private', 'shared', 'uploaded'])) {
            $folders = Folder::where($contactField, $contact->id)
                ->where('category', $folderId)
                ->where('parent_id', null)
                ->orderBy('name')
                ->get();
                
            $documents = SharedDocument::where($contactField, $contact->id)
                ->where('category', $folderId)
                ->where('folder_id', null)
                ->orderBy('created_at', 'desc')
                ->get();
                
            return response()->json([
                'folders' => $folders,
                'files' => $documents,
                'current_category' => $folderId
            ]);
        }

        // Standard folder listing for subfolders
        $folders = Folder::where('parent_id', $folderId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $documents = SharedDocument::where('folder_id', $folderId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'folders' => $folders,
            'files' => $documents,
            'current_folder' => Folder::with('shares')->find($folderId)
        ]);
    }

    /**
     * Show a single document.
     */
    public function showDocument(SharedDocument $document)
    {
        return response()->json($document->load('uploader:id,name', 'folder', 'client', 'tc'));
    }

    /**
     * Update a folder.
     */
    public function updateFolder(Request $request, Folder $folder)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:internal,shared',
            'is_active' => 'nullable|boolean'
        ]);

        $folder->update($request->only(['name', 'type', 'is_active']));

        return response()->json($folder);
    }

    /**
     * Update a document.
     */
    public function update(Request $request, SharedDocument $document)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_pinned' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        $document->update($request->only(['name', 'description', 'is_pinned', 'is_active']));

        return response()->json($document);
    }

    /**
     * Show subfolders and files in a specific folder.
     */
    public function show(Request $request, Folder $folder)
    {
        $user = $request->user();
        $isStaff = in_array($user->role, ['super_admin', 'admin', 'staff', 'consultation_staff', 'compliance_officer']);
        
        // Basic permission check
        if (!$isStaff && $folder->type === 'internal') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $folders = Folder::where('parent_id', $folder->id)
            ->where('is_active', true)
            ->with(['creator:id,name', 'shares'])
            ->orderBy('name')
            ->get();
            
        $documents = SharedDocument::where('folder_id', $folder->id)
            ->where('is_active', true)
            ->with('uploader:id,name')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'folders' => $folders,
            'files' => $documents,
            'current_folder' => $folder->load(['parent', 'shares'])
        ]);
    }

    private function formatBytes($bytes, $precision = 2)

    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
