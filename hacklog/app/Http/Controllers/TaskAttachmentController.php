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
}
