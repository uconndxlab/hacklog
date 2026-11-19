{{-- 
    Project Header Component
    Displays project identity consistently across all project pages
    @param \App\Models\Project $project
--}}
<div class="mb-3">
    <h1 class="mb-2">{{ $project->name }}</h1>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        @php
            $canChangeProjectStatus = auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isTeam());
            $headerStatusDefinitions = \App\Models\Project::statusDefinitions();
        @endphp
        @if($canChangeProjectStatus)
            <div class="position-relative d-inline-block" data-project-status-picker
                 data-status-url="{{ route('projects.status.move', $project) }}"
                 data-csrf="{{ csrf_token() }}">
                <button type="button" class="badge border-0"
                        style="background-color: {{ $project->statusColor() }}; color: {{ $project->statusTextColor() }}"
                        aria-haspopup="true" aria-expanded="false" data-status-toggle>
                    {{ $project->statusLabel() }}
                </button>
                <div class="position-absolute top-100 start-0 mt-1 p-1 bg-white border rounded shadow-sm"
                     style="display:none; z-index:1050; min-width: 10rem" data-status-menu>
                    @foreach($headerStatusDefinitions as $statusDefinition)
                        <button type="button" class="btn btn-sm w-100 text-start mb-1" data-status-option
                                data-status="{{ $statusDefinition->key }}"
                                data-label="{{ $statusDefinition->name }}"
                                data-color="{{ $statusDefinition->color }}"
                                data-text-color="{{ $statusDefinition->textColor() }}">
                            {{ $statusDefinition->name }}
                        </button>
                    @endforeach
                </div>
                <span class="visually-hidden" role="status" aria-live="polite" data-status-feedback></span>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-project-status-picker]').forEach(picker => {
                    const toggle = picker.querySelector('[data-status-toggle]');
                    const menu = picker.querySelector('[data-status-menu]');
                    const feedback = picker.querySelector('[data-status-feedback]');
                    const closeMenu = () => {
                        menu.style.display = 'none';
                        toggle.setAttribute('aria-expanded', 'false');
                    };

                    toggle.addEventListener('click', () => {
                        const isOpen = menu.style.display !== 'none';
                        menu.style.display = isOpen ? 'none' : 'block';
                        toggle.setAttribute('aria-expanded', String(!isOpen));
                    });

                    picker.querySelectorAll('[data-status-option]').forEach(option => {
                        option.addEventListener('click', async () => {
                            closeMenu();
                            toggle.disabled = true;
                            feedback.textContent = 'Saving project status';
                            try {
                                const response = await fetch(picker.dataset.statusUrl, {
                                    method: 'PATCH',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': picker.dataset.csrf,
                                    },
                                    body: JSON.stringify({ status: option.dataset.status }),
                                });
                                if (!response.ok) throw new Error('Unable to update status');
                                toggle.textContent = option.dataset.label;
                                toggle.style.backgroundColor = option.dataset.color;
                                toggle.style.color = option.dataset.textColor;
                                feedback.textContent = 'Project status saved';
                            } catch (error) {
                                feedback.textContent = 'Could not save project status';
                            } finally {
                                toggle.disabled = false;
                            }
                        });
                    });

                    document.addEventListener('click', event => {
                        if (!picker.contains(event.target)) closeMenu();
                    });
                    document.addEventListener('keydown', event => {
                        if (event.key === 'Escape') closeMenu();
                    });
                });
            });
            </script>
        @else
            <span class="badge" style="background-color: {{ $project->statusColor() }}; color: {{ $project->statusTextColor() }}">
                {{ $project->statusLabel() }}
            </span>
        @endif
        <span class="text-muted small">
            Created {{ $project->created_at->format('M j, Y') }}
        </span>

        @foreach($project->tags as $tag)
            <span class="badge rounded-pill text-bg-light border">
                {{ $tag->name }}
            </span>
        @endforeach

        @if($project->projectTypeLabel())
            <span class="badge rounded-pill text-bg-secondary">{{ $project->projectTypeLabel() }}</span>
        @endif
    </div>
</div>
