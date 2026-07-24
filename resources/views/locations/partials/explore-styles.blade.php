{{-- Shared styles for Jelajahi Lokasi multi-page flow --}}
<style>
:root {
    --lex-primary: #1F3C88;
    --lex-secondary: #2E7D32;
    --lex-border: #E5E7EB;
    --lex-muted: #64748b;
    --lex-anim: .25s ease-in-out;
}
.lex-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1rem;
}
@media (min-width: 768px) {
    .lex-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1200px) {
    .lex-grid.lex-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .lex-grid.lex-grid--4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.lex-search-wrap { max-width: 28rem; margin-bottom: 1rem; }
.lex-card {
    --lex-accent: var(--lex-primary);
    background: #fff;
    border: 1px solid var(--lex-border);
    border-left: 4px solid var(--lex-accent);
    border-radius: 1rem;
    box-shadow: 0 4px 14px rgba(15, 23, 42, .06);
    padding: 1.1rem 1.15rem 1rem;
    height: 100%;
    text-decoration: none !important;
    color: inherit;
    display: flex;
    flex-direction: column;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.lex-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(15, 23, 42, .12);
    color: inherit;
}
.lex-card:focus-visible {
    outline: 2px solid var(--lex-accent);
    outline-offset: 2px;
}
.lex-card__top {
    display: flex;
    align-items: flex-start;
    gap: .85rem;
    margin-bottom: .85rem;
}
.lex-card__icon {
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: color-mix(in srgb, var(--lex-accent) 14%, #fff);
    color: var(--lex-accent);
    font-size: 1.25rem;
}
.lex-card__title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
    margin: 0 0 .3rem;
}
.lex-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    align-items: center;
}
.lex-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: .18rem .65rem;
    font-size: .72rem;
    font-weight: 700;
}
.lex-badge--code {
    background: color-mix(in srgb, var(--lex-accent) 12%, #fff);
    color: var(--lex-accent);
    border: 1px solid color-mix(in srgb, var(--lex-accent) 28%, #fff);
}
.lex-badge--soft {
    background: #f1f5f9;
    color: #334155;
}
.lex-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
    margin-bottom: .85rem;
    flex: 1 1 auto;
}
.lex-stats--3 { grid-template-columns: repeat(3, 1fr); }
.lex-stat {
    display: flex;
    align-items: center;
    gap: .45rem;
    background: #f8fafc;
    border: 1px solid #eef2f7;
    border-radius: .7rem;
    padding: .5rem .55rem;
}
.lex-stat__icon {
    width: 1.7rem;
    height: 1.7rem;
    border-radius: .5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--lex-accent) 12%, #fff);
    color: var(--lex-accent);
    font-size: .8rem;
    flex: 0 0 auto;
}
.lex-stat__label {
    display: block;
    font-size: .65rem;
    color: var(--lex-muted);
    font-weight: 600;
    line-height: 1.1;
}
.lex-stat__value {
    display: block;
    font-size: .9rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.lex-cta {
    width: 100%;
    border: 0;
    border-radius: .75rem;
    padding: .65rem 1rem;
    font-weight: 700;
    font-size: .9rem;
    background: var(--lex-accent);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    margin-top: auto;
    pointer-events: none;
}
.lex-card:hover .lex-cta { filter: brightness(1.06); }
.lex-empty {
    grid-column: 1 / -1;
    background: #fff;
    border: 1px dashed var(--lex-border);
    border-radius: 1rem;
    padding: 2rem 1.25rem;
    text-align: center;
    color: var(--lex-muted);
}
.lex-acc .accordion-item {
    border: 1px solid var(--lex-border);
    border-left: 3px solid var(--lex-secondary);
    border-radius: .65rem !important;
    margin-bottom: .65rem;
    overflow: hidden;
    background: #fff;
}
.lex-acc .accordion-button {
    font-weight: 700;
    background: #fff;
    box-shadow: none !important;
    gap: .65rem;
}
.lex-acc .accordion-button:not(.collapsed) {
    background: #f8fafc;
    color: var(--lex-primary);
}
.lex-acc .accordion-button::after {
    transition: transform var(--lex-anim);
}
.lex-acc .accordion-body { padding: .75rem 1rem 1rem; background: #fcfcfd; }
.lex-acc-sub .accordion-item {
    border-left-color: #94a3b8;
    margin-bottom: .5rem;
}
.lex-acc-sub .accordion-button { font-weight: 650; font-size: .95rem; }
.lex-acc__count {
    margin-left: auto;
    margin-right: .5rem;
    background: #e8f5e9;
    color: #2E7D32;
    border-radius: 999px;
    padding: .15rem .65rem;
    font-size: .75rem;
    font-weight: 700;
}
.lex-acc-sub .lex-acc__count {
    background: #f1f5f9;
    color: #475569;
}
.lex-items-wrap {
    background: #fff;
    border: 1px solid var(--lex-border);
    border-radius: .65rem;
    overflow: hidden;
}
.lex-hidden { display: none !important; }
[data-theme="dark"] .lex-card,
[data-theme="dark"] .lex-acc .accordion-item,
[data-theme="dark"] .lex-acc .accordion-button,
[data-theme="dark"] .lex-items-wrap,
[data-theme="dark"] .lex-empty,
body.dark-mode .lex-card,
body.dark-mode .lex-acc .accordion-item,
body.dark-mode .lex-acc .accordion-button,
body.dark-mode .lex-items-wrap,
body.dark-mode .lex-empty {
    background: #111827;
    border-color: #334155;
    color: #e2e8f0;
}
[data-theme="dark"] .lex-card__title,
[data-theme="dark"] .lex-stat__value,
body.dark-mode .lex-card__title,
body.dark-mode .lex-stat__value { color: #f8fafc; }
[data-theme="dark"] .lex-stat,
body.dark-mode .lex-stat { background: #1f2937; border-color: #334155; }
[data-theme="dark"] .lex-acc .accordion-body,
body.dark-mode .lex-acc .accordion-body { background: #0f172a; }
[data-theme="dark"] .lex-acc .accordion-button:not(.collapsed),
body.dark-mode .lex-acc .accordion-button:not(.collapsed) { background: #1f2937; }
</style>
