@extends('layouts.app')

@section('title', 'About Hacklog')

@push('scripts')
<style>
    /* ── About page layout ──────────────────────────── */
    .hl-about-hero {
        background: #1a1d23;
        color: #e8eaee;
        border-radius: .5rem;
        margin-bottom: 2.5rem;
        padding: 3rem 2.5rem 2.5rem;
    }
    body.theme-dark .hl-about-hero {
        background: #0f1117;
        border: 1px solid #2a2f3a;
    }
    .hl-about-hero .hl-hero-eyebrow {
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #6c8ebf;
        margin-bottom: .6rem;
    }
    .hl-about-hero h1 {
        font-size: 2.4rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: .5rem;
        line-height: 1.2;
    }
    .hl-about-hero .hl-hero-sub {
        font-size: 1.1rem;
        color: #9aa5b8;
        max-width: 560px;
        line-height: 1.65;
    }

    /* ── Sticky sidebar ─────────────────────────────── */
    .hl-toc {
        position: sticky;
        top: 1.5rem;
    }
    .hl-toc-label {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: .75rem;
    }
    .hl-toc .nav-link {
        font-size: .82rem;
        color: #6c757d;
        padding: .3rem .75rem;
        border-left: 2px solid transparent;
        transition: border-color .15s, color .15s;
    }
    .hl-toc .nav-link:hover,
    .hl-toc .nav-link.active {
        color: #0d6efd;
        border-left-color: #0d6efd;
    }
    body.theme-dark .hl-toc .nav-link { color: #7d8590; }
    body.theme-dark .hl-toc .nav-link:hover,
    body.theme-dark .hl-toc .nav-link.active { color: #7ba3ff; border-left-color: #7ba3ff; }

    /* ── Section headings ───────────────────────────── */
    .hl-section-anchor {
        scroll-margin-top: 1.5rem;
    }
    .hl-section-eyebrow {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: .4rem;
    }

    /* ── Callout cards ──────────────────────────────── */
    .hl-callout {
        border-left: 3px solid;
        border-radius: 0 .375rem .375rem 0;
        padding: .9rem 1.1rem;
        font-size: .9rem;
        line-height: 1.65;
    }
    .hl-callout-blue  { border-color: #0d6efd; background: #f0f4ff; }
    .hl-callout-green { border-color: #198754; background: #f0faf4; }
    .hl-callout-amber { border-color: #ffc107; background: #fffbf0; }
    .hl-callout-red   { border-color: #dc3545; background: #fff5f5; }
    body.theme-dark .hl-callout-blue  { background: #1a2033; border-color: #4d7fd4; }
    body.theme-dark .hl-callout-green { background: #0f1f17; border-color: #2a7a52; }
    body.theme-dark .hl-callout-amber { background: #1e1a0a; border-color: #c79500; }
    body.theme-dark .hl-callout-red   { background: #1f0f10; border-color: #b02a37; }

    /* ── Flow diagram ───────────────────────────────── */
    .hl-flow {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .4rem;
    }
    .hl-flow-node {
        border: 1.5px solid;
        border-radius: .4rem;
        padding: .45rem 1rem;
        font-size: .82rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .hl-flow-arrow {
        color: #6c757d;
        font-size: 1rem;
        line-height: 1;
    }

    /* ── Concept grid ───────────────────────────────── */
    .hl-concept-card {
        border: 1px solid;
        border-radius: .5rem;
        padding: 1.1rem 1.2rem;
        height: 100%;
    }
    .hl-concept-card h4 {
        font-size: .92rem;
        font-weight: 700;
        margin-bottom: .35rem;
    }
    .hl-concept-card p {
        font-size: .83rem;
        color: #6c757d;
        margin-bottom: 0;
        line-height: 1.6;
    }
    body.theme-dark .hl-concept-card p { color: #7d8590; }
    body.theme-dark .hl-concept-card {
        border-color: #2a2f3a;
        background: #1a1f2c;
    }

    /* ── Priority / Weight chips ────────────────────── */
    .hl-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        font-size: .78rem;
        font-weight: 600;
        padding: .3rem .7rem;
        border-radius: 99px;
        border: 1.5px solid;
        line-height: 1;
    }
    .hl-chip-high   { color: #842029; background: #f8d7da; border-color: #f1aeb5; }
    .hl-chip-medium { color: #664d03; background: #fff3cd; border-color: #ffe69c; }
    .hl-chip-low    { color: #41464b; background: #e2e3e5; border-color: #c4c8cb; }
    body.theme-dark .hl-chip-high   { color: #f1aeb5; background: #2c1215; border-color: #842029; }
    body.theme-dark .hl-chip-medium { color: #ffe69c; background: #1f1a06; border-color: #664d03; }
    body.theme-dark .hl-chip-low    { color: #9ea2a7; background: #1e2026; border-color: #41464b; }

    .hl-wchip {
        display: inline-block;
        font-size: .75rem;
        font-weight: 700;
        font-family: ui-monospace, monospace;
        padding: .2rem .55rem;
        border-radius: .3rem;
        border: 1.5px solid #ced4da;
        color: #495057;
        background: #f8f9fa;
    }
    body.theme-dark .hl-wchip { color: #b8bcc6; background: #1e2026; border-color: #3a3f4b; }

    /* ── Tips list ──────────────────────────────────── */
    .hl-tips li {
        padding: .4rem 0;
        font-size: .88rem;
        border-bottom: 1px solid #dee2e6;
        line-height: 1.6;
    }
    .hl-tips li:last-child { border-bottom: none; }
    body.theme-dark .hl-tips li { border-bottom-color: #2a2f3a; }

    /* ── Comparison table ───────────────────────────── */
    .hl-compare td, .hl-compare th {
        font-size: .85rem;
        vertical-align: top;
        padding: .6rem .8rem;
    }

    /* ── Divider ─────────────────────────────────────── */
    .hl-divider {
        border: none;
        border-top: 1px solid #dee2e6;
        margin: 2.5rem 0;
    }
    body.theme-dark .hl-divider { border-top-color: #2a2f3a; }
</style>
@endpush

@section('content')

{{-- ─────────────────────────────────────────────────── --}}
{{-- HERO                                                --}}
{{-- ─────────────────────────────────────────────────── --}}
<div class="hl-about-hero">
    <div class="hl-hero-eyebrow">Documentation &amp; Philosophy</div>
    <h1>Hacklog</h1>
    <p class="hl-hero-sub">
        A lightweight project management tool.
    </p>
</div>

{{-- ─────────────────────────────────────────────────── --}}
{{-- CONTENT + SIDEBAR                                    --}}
{{-- ─────────────────────────────────────────────────── --}}
<div class="row">

    {{-- ── Sticky sidebar TOC ──────────────────────── --}}
    <div class="col-lg-3 d-none d-lg-block">
        <nav class="hl-toc" id="hl-toc">
            <div class="hl-toc-label">On this page</div>
            <nav class="nav flex-column">
                <a class="nav-link" href="#what-is-hacklog">What is Hacklog?</a>
                <a class="nav-link" href="#core-concepts">Core Concepts</a>
                <a class="nav-link" href="#priority-vs-weight">Priority vs Weight</a>
                <a class="nav-link" href="#how-work-flows">How Work Flows</a>
                <a class="nav-link" href="#workload-visibility">Workload Visibility</a>
                <a class="nav-link" href="#dashboards">Dashboards &amp; Planning</a>
                <a class="nav-link" href="#design-philosophy">Design Philosophy</a>
                <a class="nav-link" href="#usage-patterns">Usage Patterns</a>
                <a class="nav-link" href="#future">Future Direction</a>
            </nav>
        </nav>
    </div>

    {{-- ── Main content ────────────────────────────── --}}
    <div class="col-lg-9">

        {{-- ════════════════════════════════════════════
             1. WHAT IS HACKLOG?
             ════════════════════════════════════════════ --}}
        <section id="what-is-hacklog" class="hl-section-anchor mb-5">
            <div class="hl-section-eyebrow">01</div>
            <h2 class="h3 mb-3">What is Hacklog?</h2>

            <p class="lead mb-3" style="line-height:1.7;">
                Hacklog is a project management tool designed for teams that want operational
                clarity without the bureaucracy of enterprise PM platforms.
            </p>

            <p class="mb-3" style="line-height:1.7;">
                It's built around a simple belief: <strong>small teams need visibility, not process.</strong>
                The goal is to make it easy to see what's happening, what matters most, and where
                the work is piling up — without creating new work just to describe the work.
            </p>

            <div class="hl-callout hl-callout-blue mb-3">
                Hacklog is not Jira. It doesn't have sprints, epics, story points ceremonies,
                burndown ceremonies, or 14-field ticket templates. It has projects, phases,
                tasks, priorities, and weights — and that's intentionally enough.
            </div>

            <p style="line-height:1.7;">
                The name is a nod to the engineering culture it was built for: fast-moving,
                practical, slightly scrappy, and focused on shipping things that matter.
            </p>
        </section>

        <hr class="hl-divider">

        {{-- ════════════════════════════════════════════
             2. CORE CONCEPTS
             ════════════════════════════════════════════ --}}
        <section id="core-concepts" class="hl-section-anchor mb-5">
            <div class="hl-section-eyebrow">02</div>
            <h2 class="h3 mb-3">Core Concepts</h2>

            <p class="mb-4" style="line-height:1.7;">
                Hacklog is organized around a small, deliberate hierarchy. Everything fits inside
                one of these layers — and each layer has a clear purpose.
            </p>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-4">
                    <div class="hl-concept-card" style="border-color:#cfe2ff; background:#f0f6ff;">
                        <h4>Projects</h4>
                        <p>The top-level container. A project represents a body of work with a goal,
                        a team, and a lifecycle. It has a status, a description, and collects
                        everything underneath it.</p>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="hl-concept-card" style="border-color:#d1e7dd; background:#f0faf4;">
                        <h4>Phases</h4>
                        <p>Phases divide a project into logical stages — Discovery, Design, Build,
                        Launch, etc. Each phase has its own status, timeline, and set of tasks.
                        They make progress visible at a higher level.</p>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="hl-concept-card" style="border-color:#fff3cd; background:#fffbf0;">
                        <h4>Tasks</h4>
                        <p>The unit of work. Tasks live inside phases, carry a priority and weight,
                        and belong to one or more team members. Each task has two independent
                        coordinates: a <strong>status</strong> (planned, active, awaiting feedback,
                        completed) and a <strong>column</strong> — where it sits on the board.
                        These are intentionally separate.</p>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="hl-concept-card" style="border-color:#e2d9f3; background:#f9f6ff;">
                        <h4>Columns</h4>
                        <p>Each project defines its own kanban workflow — a set of columns that
                        reflect how <em>your team</em> describes in-progress work. Examples:
                        Backlog, In Progress, Review, Blocked, Done. Columns are flexible
                        labels for workflow position and don't need to match task statuses
                        one-to-one.</p>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="hl-concept-card" style="border-color:#f8d7da; background:#fff5f5;">
                        <h4>Assignments</h4>
                        <p>Tasks can be assigned to one or more team members. Assignments distribute
                        ownership and make individual workload visible. Unassigned high-priority
                        work surfaces as a planning signal.</p>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="hl-concept-card" style="border-color:#dee2e6; background:#f8f9fa;">
                        <h4>Sharing</h4>
                        <p>Projects can be shared with clients or external collaborators at a read
                        level. Shared users see the project and its tasks without access to the
                        broader application.</p>
                    </div>
                </div>
            </div>

            {{-- Hierarchy visual --}}
            <div class="card mb-0">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-semibold" style="font-size:.8rem;">Work Hierarchy</h6>
                </div>
                <div class="card-body py-3">
                    <div class="hl-flow">
                        <div class="hl-flow-node" style="color:#0d6efd; border-color:#84b4f8;">Project</div>
                        <div class="hl-flow-arrow">→</div>
                        <div class="hl-flow-node" style="color:#198754; border-color:#86c49d;">Phases</div>
                        <div class="hl-flow-arrow">→</div>
                        <div class="hl-flow-node" style="color:#664d03; border-color:#ffe69c;">Tasks</div>
                        <div class="hl-flow-arrow">→</div>
                        <div class="hl-flow-node" style="color:#495057; border-color:#ced4da;">Column (board position)</div>
                        <div class="hl-flow-arrow">→</div>
                        <div class="hl-flow-node" style="color:#6f42c1; border-color:#c5a8f0;">Assignees</div>
                    </div>
                </div>
            </div>
        </section>

        <hr class="hl-divider">

        {{-- ════════════════════════════════════════════
             3. PRIORITY VS WEIGHT
             ════════════════════════════════════════════ --}}
        <section id="priority-vs-weight" class="hl-section-anchor mb-5">
            <div class="hl-section-eyebrow">03</div>
            <h2 class="h3 mb-3">Priority vs Weight</h2>

            <p class="mb-4" style="line-height:1.7;">
                Most tools conflate urgency and effort. Hacklog separates them into two independent
                signals that together tell a much more useful story.
            </p>

            <div class="row g-4 mb-4">
                {{-- Priority --}}
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h3 class="h6 mb-0 fw-semibold">Priority — <em>Urgency &amp; Importance</em></h3>
                        </div>
                        <div class="card-body">
                            <p class="mb-3" style="font-size:.88rem; line-height:1.65;">
                                Priority answers: <strong>"How much does this matter right now?"</strong>
                                It reflects business urgency, stakeholder impact, or risk.
                                It does not say anything about how hard the task is.
                            </p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="hl-chip hl-chip-high">High</span>
                                <span class="hl-chip hl-chip-medium">Medium</span>
                                <span class="hl-chip hl-chip-low">Low</span>
                            </div>
                            <ul class="list-unstyled hl-tips mb-0">
                                <li><strong>High</strong> — blocks something or carries real risk if delayed</li>
                                <li><strong>Medium</strong> — important but not on fire</li>
                                <li><strong>Low</strong> — should happen, but not time-sensitive</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Weight --}}
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h3 class="h6 mb-0 fw-semibold">Weight — <em>Effort &amp; Cognitive Load</em></h3>
                        </div>
                        <div class="card-body">
                            <p class="mb-3" style="font-size:.88rem; line-height:1.65;">
                                Weight answers: <strong>"How heavy is this task to carry?"</strong>
                                It encodes effort, complexity, or cognitive load — not calendar time.
                                A 20-minute task that requires deep focus may be heavier than a
                                3-hour task you can do on autopilot.
                            </p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="hl-wchip">XS</span>
                                <span class="hl-wchip">S</span>
                                <span class="hl-wchip">M</span>
                                <span class="hl-wchip">L</span>
                                <span class="hl-wchip">XL</span>
                            </div>
                            <ul class="list-unstyled hl-tips mb-0">
                                <li><span class="hl-wchip" style="font-size:.7rem;">XS</span> 1pt — trivial, quick, no real thinking needed</li>
                                <li><span class="hl-wchip" style="font-size:.7rem;">S</span> 2pts — small, clear, maybe an hour</li>
                                <li><span class="hl-wchip" style="font-size:.7rem;">M</span> 3pts — a solid chunk of focused work</li>
                                <li><span class="hl-wchip" style="font-size:.7rem;">L</span> 5pts — heavy, complex, or multi-part</li>
                                <li><span class="hl-wchip" style="font-size:.7rem;">XL</span> 8pts — substantial, possibly needs decomposition</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Why both matter --}}
            <div class="hl-callout hl-callout-green mb-3">
                <strong>Why both matter:</strong> WSJF = Weighted Shortest Job First. Knowing both lets
                you make smarter decisions about what to tackle, when, and in what order.
            </div>

            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-semibold" style="font-size:.8rem;">Real-world examples</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm hl-compare mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Task</th>
                                <th>Priority</th>
                                <th>Weight</th>
                                <th class="text-muted" style="font-weight:400;">What it means</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Fix production login bug</td>
                                <td><span class="hl-chip hl-chip-high" style="font-size:.72rem;">High</span></td>
                                <td><span class="hl-wchip" style="font-size:.7rem;">XS</span></td>
                                <td class="text-muted">Drop everything — but it's a quick fix</td>
                            </tr>
                            <tr>
                                <td>Redesign onboarding flow</td>
                                <td><span class="hl-chip hl-chip-medium" style="font-size:.72rem;">Medium</span></td>
                                <td><span class="hl-wchip" style="font-size:.7rem;">XL</span></td>
                                <td class="text-muted">Important but needs dedicated blocks of time</td>
                            </tr>
                            <tr>
                                <td>Update README typo</td>
                                <td><span class="hl-chip hl-chip-low" style="font-size:.72rem;">Low</span></td>
                                <td><span class="hl-wchip" style="font-size:.7rem;">XS</span></td>
                                <td class="text-muted">Grab this when you have 5 minutes between things</td>
                            </tr>
                            <tr>
                                <td>Migrate database schema</td>
                                <td><span class="hl-chip hl-chip-high" style="font-size:.72rem;">High</span></td>
                                <td><span class="hl-wchip" style="font-size:.7rem;">L</span></td>
                                <td class="text-muted">Urgent <em>and</em> heavy — plan for this, don't squeeze it in</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <hr class="hl-divider">

        {{-- ════════════════════════════════════════════
             4. HOW WORK FLOWS
             ════════════════════════════════════════════ --}}
        <section id="how-work-flows" class="hl-section-anchor mb-5">
            <div class="hl-section-eyebrow">04</div>
            <h2 class="h3 mb-3">How Work Flows Through the System</h2>

            <p class="mb-4" style="line-height:1.7;">
                Work moves through Hacklog in a predictable path. Understanding the flow
                makes it easier to use the system well and spot where things are getting stuck.
            </p>

            <ol class="list-unstyled mb-4">
                <li class="d-flex gap-3 mb-3">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-primary text-white fw-bold"
                         style="width:2rem; height:2rem; font-size:.82rem; min-width:2rem;">1</div>
                    <div>
                        <div class="fw-semibold mb-1">A project is created and scoped</div>
                        <div class="text-muted" style="font-size:.88rem; line-height:1.6;">
                            Give it a name, description, and status. Invite team members. This becomes
                            the container everything else lives in.
                        </div>
                    </div>
                </li>
                <li class="d-flex gap-3 mb-3">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-success text-white fw-bold"
                         style="width:2rem; height:2rem; font-size:.82rem; min-width:2rem;">2</div>
                    <div>
                        <div class="fw-semibold mb-1">Phases structure the work</div>
                        <div class="text-muted" style="font-size:.88rem; line-height:1.6;">
                            Break the project into named phases (Discovery, Design, Build, QA, Launch).
                            Each phase has its own status: planned → active → completed. Phases give
                            the team a shared mental model of where you are in the project.
                        </div>
                    </div>
                </li>
                <li class="d-flex gap-3 mb-3">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle fw-bold text-white"
                         style="width:2rem; height:2rem; font-size:.82rem; min-width:2rem; background:#664d03;">3</div>
                    <div>
                        <div class="fw-semibold mb-1">Tasks define the actual work</div>
                        <div class="text-muted" style="font-size:.88rem; line-height:1.6;">
                            Add tasks to phases. Assign them to people. Set a priority and weight.
                            Optionally add a due date and description. The more complete the task,
                            the more useful the workload signals will be.
                        </div>
                    </div>
                </li>
                <li class="d-flex gap-3 mb-3">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-secondary text-white fw-bold"
                         style="width:2rem; height:2rem; font-size:.82rem; min-width:2rem;">4</div>
                    <div>
                        <div class="fw-semibold mb-1">Tasks move through the kanban board</div>
                        <div class="text-muted" style="font-size:.88rem; line-height:1.6;">
                            Each project has its own column workflow. Tasks move through columns as
                            work progresses. Columns are flexible — your team names them however
                            makes sense for your process.
                            <br><br>
                            Separately, every task also carries a <strong>status</strong>:
                            <em>planned</em>, <em>active</em>, <em>awaiting feedback</em>, or
                            <em>completed</em>. Status is a fixed semantic signal used by workload
                            metrics and dashboards; columns are the freeform workflow labels
                            visible on the board. A task can be in a "Review" column and still
                            be <em>active</em> — the two travel independently.
                        </div>
                    </div>
                </li>
                <li class="d-flex gap-3">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle fw-bold text-white"
                         style="width:2rem; height:2rem; font-size:.82rem; min-width:2rem; background:#6f42c1;">5</div>
                    <div>
                        <div class="fw-semibold mb-1">Completion closes the loop</div>
                        <div class="text-muted" style="font-size:.88rem; line-height:1.6;">
                            When a task reaches the <em>Completed</em> status, it counts toward weighted
                            completion metrics. As phases fill up with completed tasks, workload signals
                            shift and the project health picture updates automatically.
                        </div>
                    </div>
                </li>
            </ol>

            <div class="hl-callout hl-callout-amber">
                <strong>The board is not the only view.</strong> Phases have their own view with
                workload signals. Projects have a health summary. The team dashboard shows
                per-person load. The schedule and timeline views give you date-based perspectives.
                Use the view that fits the question you're asking.
            </div>
        </section>

        <hr class="hl-divider">

        {{-- ════════════════════════════════════════════
             5. WORKLOAD VISIBILITY
             ════════════════════════════════════════════ --}}
        <section id="workload-visibility" class="hl-section-anchor mb-5">
            <div class="hl-section-eyebrow">05</div>
            <h2 class="h3 mb-3">Workload Visibility</h2>

            <p class="mb-4" style="line-height:1.7;">
                Once tasks have priorities and weights, Hacklog can surface planning signals
                that go beyond simple task counts. These signals help you answer the questions
                that actually matter when managing a team.
            </p>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="h6 fw-semibold mb-2">Weighted Completion %</h4>
                            <p class="text-muted mb-0" style="font-size:.85rem; line-height:1.6;">
                                Not all tasks are created equal. A project with 9 trivial tasks
                                done and 1 XL task remaining isn't "90% done" in any meaningful sense.
                                Weighted completion gives you a truer picture of how much real work
                                is left vs how much is done.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="h6 fw-semibold mb-2">Open High-Priority Work</h4>
                            <p class="text-muted mb-0" style="font-size:.85rem; line-height:1.6;">
                                A count of open tasks marked High priority. If this number is growing
                                while completion stays flat, something is wrong with triage, capacity,
                                or scope. It's a signal worth paying attention to.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="h6 fw-semibold mb-2">Unassigned High-Priority</h4>
                            <p class="text-muted mb-0" style="font-size:.85rem; line-height:1.6;">
                                High-priority tasks with no owner are operational risk.
                                They're important, they're not getting done, and nobody is accountable.
                                This surfaces as a warning on the project homepage so it doesn't stay invisible.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="h6 fw-semibold mb-2">Assignee Workload</h4>
                            <p class="text-muted mb-0" style="font-size:.85rem; line-height:1.6;">
                                Each person's open weighted load — the sum of weight scores for all
                                their open tasks. This helps you spot when someone is overloaded
                                and when someone has capacity you can lean on.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="h6 fw-semibold mb-2">Heavy Task Concentration</h4>
                            <p class="text-muted mb-0" style="font-size:.85rem; line-height:1.6;">
                                A count of open L and XL tasks. A high concentration of heavy tasks
                                in a phase or project is a signal to check whether they're appropriately
                                staffed and whether any should be broken down further.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="h6 fw-semibold mb-2">Remaining Weight</h4>
                            <p class="text-muted mb-0" style="font-size:.85rem; line-height:1.6;">
                                Total weight score of all open tasks. Comparing remaining weight
                                across phases tells you which phases are heaviest — and helps
                                answer "what do we tackle next?" with more than a gut feeling.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hl-callout hl-callout-blue">
                These signals are only as good as the data going in. Teams that consistently set
                priority and weight on tasks get genuinely useful planning signals. Teams that
                skip them get task counts — which are much less useful.
            </div>
        </section>

        <hr class="hl-divider">

        {{-- ════════════════════════════════════════════
             6. DASHBOARDS & PLANNING
             ════════════════════════════════════════════ --}}
        <section id="dashboards" class="hl-section-anchor mb-5">
            <div class="hl-section-eyebrow">06</div>
            <h2 class="h3 mb-3">Dashboards &amp; Planning</h2>

            <p class="mb-4" style="line-height:1.7;">
                Hacklog has several views designed to give you different slices of operational
                intelligence. None of them are vanity metrics. They're all oriented toward
                the question: <strong>what do I need to know to make a good decision right now?</strong>
            </p>

            <div class="table-responsive mb-4">
                <table class="table table-sm hl-compare">
                    <thead class="table-light">
                        <tr>
                            <th>View</th>
                            <th>Audience</th>
                            <th>What it answers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold">Dashboard</td>
                            <td class="text-muted">Individual</td>
                            <td class="text-muted">What's assigned to me? What's overdue? What's unassigned and high-priority?</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Project Home</td>
                            <td class="text-muted">Project leads</td>
                            <td class="text-muted">How healthy is this project? What's remaining? Who's carrying the load?</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Phase View</td>
                            <td class="text-muted">Working teams</td>
                            <td class="text-muted">What's the workload in this phase? Who's overloaded? What's high-priority?</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Board</td>
                            <td class="text-muted">Working teams</td>
                            <td class="text-muted">What's the status of tasks right now? What needs to move?</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Team Dashboard</td>
                            <td class="text-muted">Managers / leads</td>
                            <td class="text-muted">How is each person loaded? Where is work concentrated? Who's under/over capacity?</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Schedule</td>
                            <td class="text-muted">Everyone</td>
                            <td class="text-muted">What's due soon? What's overdue across all projects?</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Timeline</td>
                            <td class="text-muted">Project leads</td>
                            <td class="text-muted">What does the project calendar look like across phases and tasks?</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="hl-callout hl-callout-green">
                The goal of every dashboard in Hacklog is <strong>actionable visibility</strong>.
            </div>
        </section>

        <hr class="hl-divider">

        {{-- ════════════════════════════════════════════
             7. DESIGN PHILOSOPHY
             ════════════════════════════════════════════ --}}
        <section id="design-philosophy" class="hl-section-anchor mb-5">
            <div class="hl-section-eyebrow">07</div>
            <h2 class="h3 mb-3">Design Philosophy</h2>

            <p class="mb-4" style="line-height:1.7;">
                Hacklog was built with a deliberate set of constraints. These aren't gaps —
                they're intentional choices.
            </p>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="hl-callout hl-callout-blue h-100" style="border-radius:.375rem;">
                        <div class="fw-semibold mb-2">What Hacklog is</div>
                        <ul class="mb-0 ps-3" style="font-size:.85rem; line-height:2;">
                            <li>Fast to set up and fast to use</li>
                            <li>Focused on small teams (2–20 people)</li>
                            <li>Built around daily, practical use</li>
                            <li>Transparent about work and workload</li>
                            <li>Opinionated enough to provide structure</li>
                            <li>Flexible enough to not get in your way</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="hl-callout hl-callout-red h-100" style="border-radius:.375rem;">
                        <div class="fw-semibold mb-2">What Hacklog is not</div>
                        <ul class="mb-0 ps-3" style="font-size:.85rem; line-height:2;">
                            <li>A replacement for Jira at scale</li>
                            <li>A sprint/ceremony management tool</li>
                            <li>A time-tracking system</li>
                            <li>A document/wiki platform</li>
                            <li>A resource forecasting tool</li>
                            <li>A substitute for human judgment</li>
                        </ul>
                    </div>
                </div>
            </div>

            <p style="line-height:1.7;">
                The deliberate simplicity is a feature. Every field in Hacklog exists because
                it provides real value to a working team. Nothing exists just to look like enterprise software.
                When in doubt, the answer was "don't add it" — not "add it just in case."
            </p>
        </section>

        <hr class="hl-divider">

        {{-- ════════════════════════════════════════════
             8. USAGE PATTERNS
             ════════════════════════════════════════════ --}}
        <section id="usage-patterns" class="hl-section-anchor mb-5">
            <div class="hl-section-eyebrow">08</div>
            <h2 class="h3 mb-3">Suggested Usage Patterns</h2>

            <p class="mb-4" style="line-height:1.7;">
                You can use Hacklog however you like. But these patterns tend to produce the
                most useful signal and the least friction.
            </p>

            <div class="accordion mb-4" id="usageAccordion">

                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#tip1">
                            Creating tasks that are actually useful
                        </button>
                    </h3>
                    <div id="tip1" class="accordion-collapse collapse show" data-bs-parent="#usageAccordion">
                        <div class="accordion-body" style="font-size:.88rem; line-height:1.7;">
                            <p>A good task title describes the outcome, not the activity.
                            "Update user profile page" is better than "Work on profile."
                            "Fix login redirect bug in Safari" is better than "Safari bug."</p>
                            <p class="mb-0">Set priority and weight on every task. A task without these fields
                            is a task that doesn't contribute to workload signals — which means
                            it's invisible to planning tools. Even a rough estimate is better than none.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tip2">
                            Sizing work with weight
                        </button>
                    </h3>
                    <div id="tip2" class="accordion-collapse collapse" data-bs-parent="#usageAccordion">
                        <div class="accordion-body" style="font-size:.88rem; line-height:1.7;">
                            <p>You don't need to be precise. The goal isn't t-shirt sizing perfection —
                            it's relative calibration. If one task is clearly heavier than another,
                            give it a higher weight. That's enough.</p>
                            <p>XL tasks (8 pts) are a signal to consider decomposition. If a task is
                            XL, ask: can this be broken into 2–3 smaller tasks without losing coherence?
                            Smaller tasks move through the board faster and keep workload signals fresher.</p>
                            <p class="mb-0">For a brand-new project, a useful starting heuristic: XS = minutes,
                            S = hours, M = a day, L = a few days, XL = a week+.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tip3">
                            Structuring phases well
                        </button>
                    </h3>
                    <div id="tip3" class="accordion-collapse collapse" data-bs-parent="#usageAccordion">
                        <div class="accordion-body" style="font-size:.88rem; line-height:1.7;">
                            <p>Phases work best when they represent a meaningful stage with a clear
                            start and end. "Phase 1" tells you nothing. "Discovery," "Design," "Build v1.0,"
                            "QA & Launch" — those tell a story about where you are in the project lifecycle.</p>
                            <p>Keep active phases to a minimum. Having 6 "active" phases simultaneously
                            is usually a sign of scope creep or poor sequencing. One or two active phases
                            at a time is healthier for most teams.</p>
                            <p class="mb-0">When a phase is done, mark it completed. This keeps the project home
                            view clean and makes the active phase count meaningful.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tip4">
                            Assigning work and distributing ownership
                        </button>
                    </h3>
                    <div id="tip4" class="accordion-collapse collapse" data-bs-parent="#usageAccordion">
                        <div class="accordion-body" style="font-size:.88rem; line-height:1.7;">
                            <p>Every high-priority task should have an owner. Unassigned high-priority
                            tasks are a planning failure that Hacklog will surface as a warning —
                            but better to never let them exist in the first place.</p>
                            <p>Tasks can have multiple assignees when the work genuinely requires
                            collaboration. Avoid using multi-assignment as a way to spread blame —
                            diffuse ownership often means no ownership.</p>
                            <p class="mb-0">Check the assignee workload tables on project and phase views when
                            distributing new work. If someone has 40+ weighted points open and
                            another has 5, that's a distribution problem worth addressing.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tip5">
                            Keeping projects healthy
                        </button>
                    </h3>
                    <div id="tip5" class="accordion-collapse collapse" data-bs-parent="#usageAccordion">
                        <div class="accordion-body" style="font-size:.88rem; line-height:1.7;">
                            <p>A healthy project has: low overdue count, few unassigned high-priority tasks,
                            steady weighted completion progress, and workload reasonably distributed
                            across the team.</p>
                            <p>Unhealthy signals to watch for: weighted completion is stuck despite
                            activity, one person is carrying 70%+ of the load, or the high-priority
                            count keeps climbing without corresponding completion.</p>
                            <p class="mb-0">The project home page and phase workload cards are designed to
                            surface these issues passively — you shouldn't have to go looking for them.</p>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <hr class="hl-divider">

        {{-- ════════════════════════════════════════════
             9. FUTURE DIRECTION
             ════════════════════════════════════════════ --}}
        <section id="future" class="hl-section-anchor mb-5">
            <div class="hl-section-eyebrow">09</div>
            <h2 class="h3 mb-3">Future Direction</h2>

            <p class="mb-3" style="line-height:1.7;">
                Hacklog is a working tool, not a roadmap product. It evolves when something
                genuinely useful emerges from actual use — not from feature pressure.
            </p>

            <p class="mb-3" style="line-height:1.7;">
                Some areas being explored:
            </p>

            <ul class="hl-tips list-unstyled mb-4">
                <li><strong>Smarter workload signals</strong> — richer aggregation across projects and teams, velocity trends, and capacity forecasting without the spreadsheet</li>
                <li><strong>Operational analytics</strong> — understanding patterns over time: are high-priority tasks being resolved faster? Is weighted completion trending up or flat?</li>
                <li><strong>Richer collaboration</strong> — task comments, attachments, and activity streams already exist; expanding the collaboration surface where it's genuinely useful</li>
                <li><strong>Maintaining simplicity at scale</strong> — the hardest challenge: keeping the tool lightweight as the feature set grows, and saying no to things that add complexity without adding value</li>
            </ul>

            <div class="hl-callout hl-callout-blue">
                The north star doesn't change: <strong>small teams, operational clarity, minimal overhead.</strong>
                Any feature that moves away from that has to clear a high bar to get in.
            </div>
        </section>

        {{-- ── Footer ──────────────────────────────── --}}


    </div>{{-- /col-lg-9 --}}
</div>{{-- /row --}}

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Scrollspy for sidebar TOC
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.hl-toc .nav-link');

    if (!sections.length || !navLinks.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                navLinks.forEach(link => link.classList.remove('active'));
                const active = document.querySelector(`.hl-toc a[href="#${entry.target.id}"]`);
                if (active) active.classList.add('active');
            }
        });
    }, { rootMargin: '0px 0px -70% 0px', threshold: 0 });

    sections.forEach(s => observer.observe(s));
});
</script>
@endpush

@endsection
