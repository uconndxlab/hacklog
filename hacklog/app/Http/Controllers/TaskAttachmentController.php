<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskAttachmentController extends Controller
{
    /**
     * Upload file for Trix inline attachment during task creation (no task ID yet).
     * Stores temporarily in session until task is created.
     */
    public function uploadForTrixTemporary(Request $request, $project)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,csv,xlsx,xls',
        ]);

        $file = $request->file('file');
        
        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::random(40) . '.' . $extension;
        
        // Store in temporary location
        $path = $file->storeAs(
            'task_attachments/temp',
            $filename,
            'public'
        );

        // Track in session
        $tempAttachments = session()->get('temp_task_attachments', []);
        $tempAttachments[] = [
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ];
        session()->put('temp_task_attachments', $tempAttachments);

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'href' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Upload file for Trix inline attachment.
     * Returns JSON with URL for Trix editor.
     */
    public function uploadForTrix(Request $request, $project, Task $task)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,csv,xlsx,xls',
        ]);

        $file = $request->file('file');
        $attachment = $this->storeAttachment($task, $file);

        return response()->json([
            'url' => $attachment->getUrl(),
            'href' => $attachment->getUrl(),
        ]);
    }

    /**
     * Upload standalone attachment.
     * Returns JSON with attachment data for dynamic insertion.
     */
    public function upload(Request $request, $project, Task $task)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,csv,xlsx,xls',
        ]);

        $file = $request->file('file');
        $attachment = $this->storeAttachment($task, $file);

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'size' => $attachment->size,
                'user_name' => $attachment->user->name,
                'download_url' => route('projects.board.tasks.attachments.download', [$project, $task, $attachment]),
                'delete_url' => route('projects.board.tasks.attachments.destroy', [$project, $task, $attachment]),
            ]);
        }

        // Fallback redirect for non-AJAX requests
        return redirect()
            ->route('projects.board.tasks.edit', [$project, $task])
            ->with('activeTab', 'attachments');
    }

    /**
     * Download an attachment.
     */
    public function download($project, Task $task, TaskAttachment $attachment)
    {
        // Ensure attachment belongs to this task
        if ($attachment->task_id !== $task->id) {
            abort(403);
        }

        $path = Storage::disk('public')->path($attachment->getStoragePath());

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $attachment->original_name);
    }

    /**
     * Delete an attachment.
     */
    public function destroy($project, Task $task, TaskAttachment $attachment)
    {
        // Ensure attachment belongs to this task
        if ($attachment->task_id !== $task->id) {
            abort(403);
        }

        // Only uploader can delete their own attachment
        if ($attachment->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        $attachment->deleteFile();
        $attachment->delete();

        // Return JSON for AJAX requests
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'message' => 'Attachment deleted successfully',
            ]);
        }

        // Fallback redirect for non-AJAX requests
        return redirect()
            ->route('projects.board.tasks.edit', [$project, $task])
            ->with('activeTab', 'attachments');
    }

    /**
     * Store attachment file and create database record.
     */
    private function storeAttachment(Task $task, $file): TaskAttachment
    {
        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::random(40) . '.' . $extension;
        
        // Store in public disk under task_attachments/{task_id}/
        $path = $file->storeAs(
            "task_attachments/{$task->id}",
            $filename,
            'public'
        );

        // Create database record
        return TaskAttachment::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Process temporary attachments after task creation.
     * Moves files from temp storage to task storage and creates DB records.
     * Also updates the task description to use permanent URLs.
     */
    public static function processTempAttachments(Task $task): void
    {
        $tempAttachments = session()->get('temp_task_attachments', []);
        
        if (empty($tempAttachments)) {
            return;
        }

        $description = $task->description;
        $urlReplacements = [];

        foreach ($tempAttachments as $tempAttachment) {
            $oldPath = $tempAttachment['path'];
            $filename = basename($oldPath);
            $newPath = "task_attachments/{$task->id}/{$filename}";
            
            // Move file from temp to task-specific directory
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->move($oldPath, $newPath);
                
                // Create database record
                TaskAttachment::create([
                    'task_id' => $task->id,
                    'user_id' => auth()->id(),
                    'filename' => $filename,
                    'original_name' => $tempAttachment['original_name'],
                    'mime_type' => $tempAttachment['mime_type'],
                    'size' => $tempAttachment['size'],
                ]);

                // Track URL replacement for description update
                $oldUrl = Storage::disk('public')->url($oldPath);
                $newUrl = Storage::disk('public')->url($newPath);
                $urlReplacements[$oldUrl] = $newUrl;
            }
        }

        // Update description with new URLs
        if (!empty($urlReplacements) && $description) {
            foreach ($urlReplacements as $oldUrl => $newUrl) {
                $description = str_replace($oldUrl, $newUrl, $description);
            }
            $task->update(['description' => $description]);
        }

        // Clear session
        session()->forget('temp_task_attachments');
    }
}
