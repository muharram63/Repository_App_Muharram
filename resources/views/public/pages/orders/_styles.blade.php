{{-- Общие стили страниц срочных заявок. Публичная тема: --surface, --border, --accent… --}}
<style>
    .uo-page { padding: 28px 0 56px; }
    .uo-page .container { max-width: 880px; }
    .uo-back { display: inline-flex; gap: 6px; font-size: 14px; font-weight: 600; color: var(--text-muted); margin-bottom: 16px; text-decoration: none; }
    .uo-back:hover { color: var(--accent); }
    .uo-h1 { font-size: clamp(22px, 3vw, 30px); font-weight: 800; margin: 0 0 6px; color: var(--text); }
    .uo-lead { color: var(--text-muted); font-size: 14.5px; margin: 0 0 20px; line-height: 1.55; }
    .uo-card { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 22px; margin-bottom: 16px; color: var(--text); }
    .uo-flash { margin-bottom: 16px; padding: 12px 16px; border-radius: 12px; font-weight: 600; font-size: 14px; }
    .uo-flash.ok { background: #ECFDF5; color: #15803D; }
    .uo-flash.bad { background: #FEF2F2; color: #B91C1C; }
    .uo-flash ul { margin: 0; padding-left: 18px; }

    .uo-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 800; white-space: nowrap; }
    .uo-badge.urgent { background: #FEE2E2; color: #B91C1C; }
    .uo-badge.status { background: var(--accent-soft); color: var(--accent-text); }
    .uo-badge.closed { background: var(--surface-alt, #F1F3F8); color: var(--text-muted); }
    .uo-badge.done { background: #DCFCE7; color: #15803D; }

    .uo-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 11px 18px; border-radius: 12px;
        font: inherit; font-weight: 700; font-size: 14.5px; cursor: pointer; text-decoration: none;
        border: 1px solid var(--border); background: var(--surface); color: var(--text);
    }
    .uo-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
    .uo-btn.danger { color: #DC2626; }
    .uo-btn:disabled { opacity: .6; cursor: progress; }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .uo-badge.urgent { background: #3B1515; color: #FCA5A5; }
        :root:not([data-theme="light"]) .uo-badge.done { background: #12301F; color: #86EFAC; }
        :root:not([data-theme="light"]) .uo-flash.ok { background: #12301F; color: #86EFAC; }
        :root:not([data-theme="light"]) .uo-flash.bad { background: #3B1515; color: #FCA5A5; }
    }
    :root[data-theme="dark"] .uo-badge.urgent { background: #3B1515; color: #FCA5A5; }
    :root[data-theme="dark"] .uo-badge.done { background: #12301F; color: #86EFAC; }
    :root[data-theme="dark"] .uo-flash.ok { background: #12301F; color: #86EFAC; }
    :root[data-theme="dark"] .uo-flash.bad { background: #3B1515; color: #FCA5A5; }

    @media (max-width: 640px) {
        .uo-page { padding: 14px 0 40px; }
        .uo-card { padding: 16px; border-radius: 14px; }
    }
</style>

@if(session('status'))
    <div class="uo-flash ok">{{ session('status') }}</div>
@endif
@if(session('error'))
    <div class="uo-flash bad">{{ session('error') }}</div>
@endif
