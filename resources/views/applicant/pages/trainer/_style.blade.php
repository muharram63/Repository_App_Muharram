{{-- Оформление страниц тренажёра — на токенах кабинета: тёмная тема работает сама. --}}
<style>
    body { font-family: 'Inter', sans-serif; }
    h1, h2 { font-family: 'Manrope', 'Inter', sans-serif; }
    .tr-wrap { padding: 0 3vh 4vh 2vh; display: flex; flex-direction: column; gap: 16px; max-width: 920px; }
    .tr-head { display: flex; gap: 16px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; }
    .tr-head h1 { margin: 0 0 6px; font-size: 22px; color: var(--ink); }
    .tr-lead { margin: 0; font-size: 13.5px; color: var(--gray-500); max-width: 640px; line-height: 1.55; }
    .tr-balance {
        display: flex; flex-direction: column; align-items: center; min-width: 110px; padding: 12px 16px;
        border-radius: var(--radius); background: var(--indigo-50); color: var(--indigo-700);
    }
    .tr-balance b { font-size: 30px; line-height: 1; }
    .tr-balance span { font-size: 12px; font-weight: 600; }
    .tr-card {
        background: var(--white); border: 1px solid var(--gray-200); border-radius: var(--radius);
        padding: 16px 18px; display: flex; flex-direction: column; gap: 12px;
    }
    .tr-card h2 { margin: 0; font-size: 16px; color: var(--ink); }
    .tr-muted { font-size: 13px; color: var(--gray-500); }
    .tr-note { font-size: 13.5px; padding: 11px 14px; border-radius: 10px; }
    .tr-good { background: var(--wash-good); color: var(--ink-good); }
    .tr-bad { background: var(--wash-danger); color: var(--danger); }
    .tr-warn { background: var(--wash-warn); color: var(--ink-warn); }
    .tr-form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .tr-form label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: var(--ink); }
    .tr-form select, .tr-form input[type=text], .tr-form textarea {
        font: inherit; font-size: 14px; padding: 9px 11px; border-radius: 10px;
        border: 1px solid var(--gray-300); background: var(--white); color: var(--ink);
    }
    .tr-form .tr-check { grid-column: 1 / -1; flex-direction: row; align-items: flex-start; font-weight: 400; gap: 10px; }
    .tr-form .tr-row { grid-column: 1 / -1; }
    @media (max-width: 640px) { .tr-form { grid-template-columns: 1fr; } }
    .tr-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .tr-btn {
        display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 999px;
        border: 1px solid transparent; background: var(--indigo-600); color: #fff; font: inherit;
        font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none;
    }
    .tr-btn:disabled { opacity: .55; cursor: default; }
    .tr-ghost { background: var(--white); color: var(--indigo-600); border-color: var(--gray-300); }
    .tr-item {
        display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;
        padding: 10px 0; border-top: 1px solid var(--gray-100);
    }
    .tr-item:first-of-type { border-top: 0; }
    .tr-score { font-weight: 800; padding: 4px 10px; border-radius: 999px; font-size: 13px; }
    .tr-score.ok { background: var(--wash-good); color: var(--ink-good); }
    .tr-score.mid { background: var(--wash-warn); color: var(--ink-warn); }
    .tr-score.low { background: var(--wash-danger); color: var(--danger); }
    .tr-packs { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
    .tr-pack {
        display: flex; flex-direction: column; gap: 6px; padding: 14px; border-radius: 12px;
        border: 1px solid var(--gray-200); background: var(--gray-100);
    }
    .tr-price { font-size: 20px; font-weight: 800; color: var(--ink); }
    .tr-tag { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: var(--gray-100); color: var(--gray-500); }
    .tr-tag.tr-confirmed { background: var(--wash-good); color: var(--ink-good); }
    .tr-tag.tr-rejected { background: var(--wash-danger); color: var(--danger); }
    .tr-tag.tr-claimed { background: var(--wash-warn); color: var(--ink-warn); }
    .tr-req { display: grid; grid-template-columns: max-content 1fr; gap: 8px 16px; font-size: 14px; }
    .tr-req dt { color: var(--gray-500); }
    .tr-req dd { margin: 0; font-weight: 700; color: var(--ink); overflow-wrap: anywhere; }
    .tr-list { margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.6; color: var(--ink); }
    .tr-list li + li { margin-top: 6px; }
</style>
