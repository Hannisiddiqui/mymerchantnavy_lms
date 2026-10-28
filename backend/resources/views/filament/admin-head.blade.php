{{-- Loaded in every admin page: styles for the course/test builders + KaTeX so formulas display as formulas. --}}
{{-- KaTeX is served from this site (public/vendor/katex, same version as the student app): no outside website is needed. --}}
<link rel="stylesheet" href="{{ asset('vendor/katex/katex.min.css') }}">
<script defer src="{{ asset('vendor/katex/katex.min.js') }}"></script>
<script defer src="{{ asset('vendor/katex/contrib/mhchem.min.js') }}"></script> {{-- chemistry formulas, e.g. ce{H2O} --}}
<script defer src="{{ asset('vendor/katex/contrib/auto-render.min.js') }}"></script>
{{-- The script that draws the formulas is its own file (public/vendor/mmn/admin-math.js). --}}
<script defer src="{{ asset('vendor/mmn/admin-math.js') }}?v={{ filemtime(public_path('vendor/mmn/admin-math.js')) }}"></script>

<style>
    /* ===== Course & test builder (prefix cb-) ===== */
    .cb-wrap { display: flex; flex-direction: column; gap: 1rem; }
    .cb-card { border: 1px solid var(--gray-200); background: #fff; border-radius: .75rem; }
    .dark .cb-card { border-color: rgba(255,255,255,.1); background: var(--gray-900); }
    .cb-muted { color: var(--gray-500); font-size: .8125rem; }
    .dark .cb-muted { color: var(--gray-400); }
    .cb-title { font-weight: 600; color: var(--gray-950); }
    .dark .cb-title { color: #fff; }
    .cb-row { display: flex; align-items: center; gap: .625rem; }
    .cb-grow { flex: 1 1 auto; min-width: 0; }
    .cb-handle { cursor: grab; color: var(--gray-400); display: inline-flex; padding: .125rem; border-radius: .25rem; flex: none; }
    .cb-handle:hover { color: var(--gray-700); background: var(--gray-100); }
    .dark .cb-handle:hover { color: #fff; background: rgba(255,255,255,.08); }
    .cb-handle svg { width: 1.125rem; height: 1.125rem; }
    .fi-sortable-ghost { opacity: .4; }

    /* summary strip */
    .cb-stats { display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; padding: .875rem 1.125rem; align-items: center; }
    .cb-stat b { font-size: 1.125rem; color: var(--gray-950); margin-right: .25rem; }
    .dark .cb-stat b { color: #fff; }
    .cb-warn { color: var(--warning-600); font-weight: 500; font-size: .8125rem; }

    /* subject */
    .cb-subject > .cb-head { padding: .875rem 1rem; }
    .cb-num { flex: none; border-radius: .5rem; background: var(--primary-50); color: var(--primary-700); font-weight: 700; font-size: .75rem; padding: .25rem .5rem; white-space: nowrap; }
    .dark .cb-num { background: rgba(59,130,246,.15); color: var(--primary-300); }
    .cb-body { border-top: 1px solid var(--gray-200); padding: .75rem 1rem 1rem 1rem; display: flex; flex-direction: column; gap: .75rem; }
    .dark .cb-body { border-color: rgba(255,255,255,.1); }
    .cb-collapse { transition: transform .15s; display: inline-flex; padding: .25rem; border-radius: .375rem; color: var(--gray-500); }
    .cb-collapse:hover { background: var(--gray-100); }
    .dark .cb-collapse:hover { background: rgba(255,255,255,.08); }
    .cb-collapse svg { width: 1.125rem; height: 1.125rem; }
    .cb-collapse.is-closed { transform: rotate(-90deg); }

    /* chapter */
    .cb-chapters { display: flex; flex-direction: column; gap: .75rem; min-height: .5rem; }
    .cb-chapter { border: 1px solid var(--gray-200); border-radius: .625rem; background: var(--gray-50); }
    .dark .cb-chapter { border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.03); }
    .cb-chapter > .cb-head { padding: .625rem .75rem; }
    .cb-flash { animation: cb-flash 2.2s ease-out; }
    @keyframes cb-flash { 0%, 40% { box-shadow: 0 0 0 3px var(--primary-500); } 100% { box-shadow: 0 0 0 3px transparent; } }

    /* content rows */
    .cb-contents { display: flex; flex-direction: column; gap: .375rem; padding: 0 .625rem .25rem .625rem; min-height: 2.5rem; }
    .cb-content { display: flex; align-items: center; gap: .625rem; padding: .5rem .625rem; border-radius: .5rem; background: #fff; border: 1px solid var(--gray-200); }
    .dark .cb-content { background: var(--gray-900); border-color: rgba(255,255,255,.1); }
    .cb-content:hover { border-color: var(--primary-300); }
    .cb-ico { flex: none; width: 2rem; height: 2rem; border-radius: .5rem; display: grid; place-items: center; background: var(--primary-50); color: var(--primary-600); }
    .cb-ico svg { width: 1.125rem; height: 1.125rem; }
    .cb-ico.t-video { background: #eff6ff; color: #2563eb; }
    .cb-ico.t-live_class { background: #fef2f2; color: #dc2626; }
    .cb-ico.t-pdf { background: #fffbeb; color: #d97706; }
    .cb-ico.t-note { background: #f0fdfa; color: #0d9488; }
    .cb-ico.t-assignment { background: #f5f3ff; color: #7c3aed; }
    .cb-ico.t-practice_test { background: #f0fdf4; color: #16a34a; }
    .cb-ico.t-mock { background: #0b1e3d; color: #fff; }
    .cb-empty { border: 1.5px dashed var(--gray-300); border-radius: .5rem; padding: .75rem; text-align: center; color: var(--gray-500); font-size: .8125rem; }
    .dark .cb-empty { border-color: rgba(255,255,255,.15); }
    .cb-footer { padding: .25rem .75rem .75rem .75rem; display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }

    /* inline add */
    .cb-add { display: flex; gap: .5rem; align-items: center; }
    .cb-input { flex: 1 1 auto; min-width: 0; border: 1px solid var(--gray-300); border-radius: .5rem; padding: .4375rem .75rem; font-size: .875rem; background: #fff; color: var(--gray-950); }
    .dark .cb-input { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.15); color: #fff; }
    .cb-input:focus { outline: 2px solid var(--primary-500); outline-offset: -1px; }

    /* mock test entry */
    .cb-mock { border-color: #0b1e3d; background: linear-gradient(90deg, rgba(11,30,61,.05), transparent); }
    .dark .cb-mock { border-color: rgba(147,197,253,.4); }
    .cb-mock .cb-head { padding: .875rem 1rem; }

    /* phones: rows wrap instead of overlapping */
    @media (max-width: 640px) {
        .cb-head, .cb-content, .qb-q { flex-wrap: wrap; }
        .cb-head > .cb-grow, .cb-content > .cb-grow { flex: 1 1 10rem; }
    }

    .cb-help { padding: 2rem 1.5rem; text-align: center; }
    .cb-help ol { text-align: left; display: inline-block; margin: 1rem 0 1.5rem; padding-left: 1.25rem; list-style: decimal; line-height: 1.9; }

    /* ===== Test question builder ===== */
    .qb-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1280px) { .qb-grid { grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); } }
    .qb-q { display: flex; align-items: flex-start; gap: .625rem; padding: .625rem .75rem; border: 1px solid var(--gray-200); border-radius: .625rem; background: #fff; }
    .dark .qb-q { background: var(--gray-900); border-color: rgba(255,255,255,.1); }
    .qb-q:hover { border-color: var(--primary-300); }
    .qb-no { flex: none; min-width: 1.75rem; height: 1.75rem; border-radius: .375rem; background: #0b1e3d; color: #fff; font-size: .75rem; font-weight: 700; display: grid; place-items: center; padding: 0 .25rem; }
    .qb-text { font-size: .875rem; line-height: 1.45; color: var(--gray-900); overflow-wrap: anywhere; max-height: 4.5rem; overflow: hidden; }
    .dark .qb-text { color: var(--gray-100); }
    .qb-text p { margin: 0; }
    .qb-tags { display: flex; flex-wrap: wrap; gap: .375rem; margin-top: .375rem; align-items: center; }
    .qb-list { display: flex; flex-direction: column; gap: .5rem; }
    .qb-scroll { max-height: 70vh; overflow-y: auto; padding-right: .25rem; }
    .qb-filters { display: grid; gap: .5rem; grid-template-columns: 1fr 1fr; padding: .75rem; }
    .qb-filters .full { grid-column: 1 / -1; }
    .qb-sticky { position: sticky; top: 5rem; }

    /* ===== Import review (prefix ri-) ===== */
    .ri-card { border: 1px solid var(--gray-200); border-left-width: 4px; border-radius: .75rem; background: #fff; padding: .875rem 1rem; }
    .dark .ri-card { background: var(--gray-900); border-color: rgba(255,255,255,.1); }
    .ri-ok { border-left-color: var(--success-500); }
    .ri-warning { border-left-color: var(--warning-500); }
    .ri-error { border-left-color: var(--danger-500); }
    .ri-done { opacity: .55; }
    .ri-options { display: grid; gap: .375rem .75rem; grid-template-columns: 1fr; margin-top: .625rem; }
    @media (min-width: 768px) { .ri-options { grid-template-columns: 1fr 1fr; } }
    .ri-opt { display: flex; gap: .5rem; align-items: baseline; border: 1px solid var(--gray-200); border-radius: .5rem; padding: .375rem .625rem; font-size: .875rem; overflow-wrap: anywhere; }
    .dark .ri-opt { border-color: rgba(255,255,255,.12); }
    .ri-opt b { color: var(--gray-500); flex: none; }
    .ri-opt.is-correct { border-color: var(--success-500); background: rgba(22,163,74,.08); }
    .ri-tick { margin-left: auto; color: var(--success-600); font-size: .75rem; font-weight: 600; white-space: nowrap; }
    .ri-warn { margin-top: .5rem; font-size: .8125rem; border-radius: .5rem; padding: .375rem .625rem; }
    .ri-warn-warning { background: rgba(245,158,11,.12); color: var(--warning-700); }
    .ri-warn-error { background: rgba(220,38,38,.1); color: var(--danger-700); }
    .ri-expl { margin-top: .5rem; font-size: .8125rem; color: var(--gray-600); }
    .ri-expl summary { cursor: pointer; font-weight: 600; color: var(--primary-600); }
    .ri-expl p { margin: .25rem 0; }
</style>
