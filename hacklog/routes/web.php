<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EpicController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectResourceController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

// CAS Authentication routes - NetID only
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::get('/login/cas', [AuthController::class, 'login'])->name('login.cas');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Public home
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Protected application routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');

    // Admin-only: User management and task cleanup
    Route::middleware('admin')->group(function () {
        Route::resource('users', UsersController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::post('users/search', [UsersController::class, 'searchUsers'])->name('users.search');
        Route::post('users/lookup-netid', [UsersController::class, 'lookupNetid'])->name('users.lookup-netid');
        Route::get('admin/projects/{project}/epics/{epic}/tasks', [TaskController::class, 'adminIndex'])->name('admin.epics.tasks.index');
        Route::delete('admin/projects/{project}/epics/{epic}/tasks/bulk', [TaskController::class, 'bulkDelete'])->name('admin.epics.tasks.bulk-delete');
    });

    Route::resource('projects', ProjectController::class);
Route::get('projects/{project}/board', [ProjectController::class, 'board'])->name('projects.board');
Route::get('projects/{project}/board/task-form', [ProjectController::class, 'taskForm'])->name('projects.board.task-form');
Route::post('projects/{project}/board/tasks', [ProjectController::class, 'storeTask'])->name('projects.board.tasks.store');
Route::get('projects/{project}/board/tasks/{task}/edit', [ProjectController::class, 'editTask'])->name('projects.board.tasks.edit');    Route::get('projects/{project}/board/tasks/{task}', [ProjectController::class, 'showTask'])->name('projects.board.tasks.show');Route::put('projects/{project}/board/tasks/{task}', [ProjectController::class, 'updateTask'])->name('projects.board.tasks.update');
Route::post('projects/{project}/tasks', [ProjectController::class, 'storeProjectTask'])->name('projects.tasks.store');
Route::get('projects/{project}/schedule', [ProjectController::class, 'schedule'])->name('projects.schedule');
Route::get('projects/{project}/timeline', [ProjectController::class, 'timeline'])->name('projects.timeline');
Route::resource('projects.epics', EpicController::class);
Route::resource('projects.epics.tasks', TaskController::class);
Route::post('projects/{project}/epics/{epic}/tasks/{task}/move-up', [TaskController::class, 'moveUp'])
    ->name('projects.epics.tasks.move-up');
Route::post('projects/{project}/epics/{epic}/tasks/{task}/move-down', [TaskController::class, 'moveDown'])
    ->name('projects.epics.tasks.move-down');
Route::resource('projects.columns', ColumnController::class)->except(['show']);
Route::resource('projects.resources', ProjectResourceController::class)->except(['show']);
Route::post('projects/{project}/resources/{resource}/move-up', [ProjectResourceController::class, 'moveUp'])
    ->name('projects.resources.move-up');
Route::post('projects/{project}/resources/{resource}/move-down', [ProjectResourceController::class, 'moveDown'])
    ->name('projects.resources.move-down');
});
