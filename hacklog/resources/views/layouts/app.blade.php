<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hacklog')</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Trix Editor CSS -->
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
    
    <!-- Hacklog Theme -->
    <link rel="stylesheet" href="{{ asset('css/hacklog-theme.css') }}">
    
    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>

</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <span class="hl-logo mx-2"></span>
            <a class="navbar-brand" href="/">Hacklog</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">Projects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('schedule.*') ? 'active' : '' }}" href="{{ route('schedule.index') }}">Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('timeline.*') ? 'active' : '' }}" href="{{ route('timeline.index') }}">Timeline</a>
                    </li>
                    @if(Auth::check() && Auth::user()->isAdmin())


                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('team.*') ? 'active' : '' }}" href="{{ route('team.dashboard') }}">Team</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('activity-log.*') ? 'active' : '' }}" href="{{ route('activity-log.index') }}">Activity Log</a>
                        </li>

                    @endif
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item d-flex align-items-center me-2">
                        <button id="hl-theme-toggle"
                                type="button"
                                class="btn btn-sm btn-outline-light hl-theme-toggle"
                                aria-label="Toggle color theme">
                            <span class="hl-theme-toggle-track">
                                <span class="hl-theme-toggle-thumb"></span>
                            </span>
                        </button>
                    </li>
                    @auth
                        <li class="nav-item">
                            <span class="nav-link text-light">{{ Auth::user()->name }}</span>
                        </li>
                        <li class="nav-item">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-link nav-link">Logout</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Login</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>

    {{-- Floating Action Button --}}
    @auth
        <button type="button" class="btn btn-primary position-fixed bottom-0 end-0 m-4 rounded-circle d-flex align-items-center justify-content-center" 
                style="width: 60px; height: 60px; z-index: 1050;" 
                data-bs-toggle="modal" 
                data-bs-target="#projectSelectionModal"
                title="Add Task">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2z"/>
            </svg>
        </button>
    @endauth

    {{-- Project Selection Modal --}}
    <div class="modal fade" id="projectSelectionModal" tabindex="-1" aria-labelledby="projectSelectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="projectSelectionModalLabel">Select Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="projectSearch" placeholder="Search projects...">
                    </div>
                    <div class="mb-3">
                        <select class="form-select" id="projectSort">
                            <option value="recent_activity">Recent Activity</option>
                            <option value="alphabetical">Alphabetical</option>
                        </select>
                    </div>
                    <div id="projectList" style="max-height: 400px; overflow-y: auto;">
                        {{-- Projects will be loaded here --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Task Creation Modal --}}
    <div class="modal fade" id="taskCreationModal" tabindex="-1" aria-labelledby="taskCreationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
                <div class="modal-header" style="flex-shrink: 0;">
                    <h5 class="modal-title" id="taskCreationModalLabel">Create Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" id="taskCreationModalContent" style="flex: 1; overflow-y: auto; display: flex; flex-direction: column;">
                    <div class="text-center py-4" style="flex: 1; display: flex; align-items: center; justify-content: center;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Trix Editor JS -->
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
    
    @stack('scripts')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // =========================
            // Theme toggle (light/dark)
            // =========================
            const body = document.body;
            const THEME_KEY = 'hacklog-theme';
            const themeToggle = document.getElementById('hl-theme-toggle');

            function applyTheme(theme) {
                if (theme === 'dark') {
                    body.classList.add('theme-dark');
                } else {
                    body.classList.remove('theme-dark');
                }
            }

            // Initial theme: saved preference or system preference
            (() => {
                try {
                    const saved = localStorage.getItem(THEME_KEY);
                    if (saved === 'dark' || saved === 'light') {
                        applyTheme(saved);
                    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        applyTheme('dark');
                    }
                } catch (_) {
                    // If localStorage is unavailable, silently ignore
                }
            })();

            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    const isDark = !body.classList.contains('theme-dark');
                    applyTheme(isDark ? 'dark' : 'light');
                    try {
                        localStorage.setItem(THEME_KEY, isDark ? 'dark' : 'light');
                    } catch (_) {
                        // Ignore persistence errors
                    }
                });
            }

            const projectSelectionModal = document.getElementById('projectSelectionModal');
            const taskCreationModal = document.getElementById('taskCreationModal');
            const projectSearch = document.getElementById('projectSearch');
            const projectSort = document.getElementById('projectSort');
            const projectList = document.getElementById('projectList');

            // Load projects when modal opens
            projectSelectionModal.addEventListener('show.bs.modal', function() {
                loadProjects();
            });

            // Focus search field when modal is fully shown
            projectSelectionModal.addEventListener('shown.bs.modal', function() {
                projectSearch.focus();
            });

            // Handle search and sort changes
            projectSearch.addEventListener('input', loadProjects);
            projectSort.addEventListener('change', loadProjects);

            function loadProjects() {
                const search = projectSearch.value;
                const sort = projectSort.value;

                fetch(`/projects?search=${encodeURIComponent(search)}&sort=${sort}&scope=all&status=active&limit=50`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    // Parse the HTML and extract project items
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const projectsContainer = doc.querySelector('#projects-list');
                    
                    if (projectsContainer) {
                        // Convert project cards to list items
                        const projectCards = projectsContainer.querySelectorAll('.col-md-6, .col-lg-4');
                        const projectItems = Array.from(projectCards).map(card => {
                            const link = card.querySelector('a');
                            const title = link ? link.textContent.trim() : 'Unknown Project';
                            const href = link ? link.href : '#';
                            const projectId = href.match(/\/projects\/(\d+)/)?.[1];
                            
                            return `
                                <div class="list-group-item list-group-item-action project-item" 
                                     data-project-id="${projectId}" 
                                     style="cursor: pointer;">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">${title}</h6>
                                    </div>
                                    <small class="text-muted">${card.querySelector('.card-text')?.textContent?.trim() || ''}</small>
                                </div>
                            `;
                        }).join('');
                        
                        projectList.innerHTML = `<div class="list-group">${projectItems}</div>`;
                        
                        // Add click handlers
                        document.querySelectorAll('.project-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const projectId = this.dataset.projectId;
                                selectProject(projectId);
                            });
                        });
                    } else {
                        projectList.innerHTML = '<p class="text-muted">No projects found.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading projects:', error);
                    projectList.innerHTML = '<p class="text-danger">Error loading projects.</p>';
                });
            }

            function selectProject(projectId) {
                // Hide project selection modal
                bootstrap.Modal.getInstance(projectSelectionModal).hide();
                
                // Show task creation modal
                const taskModal = new bootstrap.Modal(taskCreationModal);
                taskModal.show();
                
                // Load task creation form
                const taskContent = document.getElementById('taskCreationModalContent');
                taskContent.innerHTML = `
                    <div class="text-center py-4" style="flex: 1; display: flex; align-items: center; justify-content: center;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                `;
                
                fetch(`/projects/${projectId}/board/task-form?global_modal=1`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    taskContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading task form:', error);
                    taskContent.innerHTML = '<p class="text-danger">Error loading task form.</p>';
                });
            }

            // Global function for HTMX events
            window.closeModal = function(evt) {
                const xhr = evt.detail.xhr;
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Success - close modal and show success message
                            bootstrap.Modal.getInstance(taskCreationModal).hide();
                            
                            // Show success message at top of page
                            const alert = document.createElement('div');
                            alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
                            alert.style.cssText = 'top: 20px; right: 20px; z-index: 1060; min-width: 300px;';
                            alert.innerHTML = `
                                ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            `;
                            document.body.appendChild(alert);
                            
                            // Auto-remove after 5 seconds
                            setTimeout(() => {
                                if (alert.parentNode) {
                                    alert.remove();
                                }
                            }, 5000);
                        }
                    } catch (e) {
                        // Not JSON, treat as HTML (for board modal)
                        bootstrap.Modal.getInstance(taskCreationModal).hide();
                    }
                }
            };
        });
    </script>
</body>
</html>
