<x-filament-panels::page>
<div class="sdg">

{{-- ── Scoped styles ─────────────────────────────────────────────── --}}
<style>
/* Design tokens — light default */
.sdg {
  --sdg-bg:         transparent;
  --sdg-card:       #ffffff;
  --sdg-code:       #18233a;
  --sdg-border:     #e2e8f0;
  --sdg-accent:     #6366f1;
  --sdg-glow:       rgba(99,102,241,.08);
  --sdg-text:       #1e293b;
  --sdg-dim:        #475569;
  --sdg-muted:      #94a3b8;
  --sdg-s-bg:       rgba(37,99,235,.07);
  --sdg-s-fg:       #1d4ed8;
  --sdg-d-bg:       rgba(22,163,74,.07);
  --sdg-d-fg:       #15803d;
  --sdg-success:    #16a34a;
  --sdg-warning:    #d97706;
  --sdg-danger:     #dc2626;
  --sdg-radius:     8px;
}
/* Dark mode — Filament adds .dark on <html> */
.dark .sdg {
  --sdg-card:    #111827;
  --sdg-code:    #0d1421;
  --sdg-border:  #1e2845;
  --sdg-accent:  #818cf8;
  --sdg-glow:    rgba(129,140,248,.1);
  --sdg-text:    #e2e8f0;
  --sdg-dim:     #94a3b8;
  --sdg-muted:   #475569;
  --sdg-s-bg:    rgba(96,165,250,.1);
  --sdg-s-fg:    #60a5fa;
  --sdg-d-bg:    rgba(52,211,153,.1);
  --sdg-d-fg:    #34d399;
  --sdg-success: #34d399;
  --sdg-warning: #fbbf24;
  --sdg-danger:  #f87171;
}

/* Base */
.sdg * { box-sizing: border-box; }
.sdg { color: var(--sdg-text); font-family: -apple-system,'Segoe UI',system-ui,sans-serif; line-height: 1.65; }

/* ── Top nav ──────────────────────────────────────────────────────── */
.sdg-nav {
  position: sticky;
  top: 0;
  z-index: 20;
  background: var(--sdg-card);
  border: 1px solid var(--sdg-border);
  border-radius: var(--sdg-radius);
  padding: .5rem 1rem;
  margin-bottom: 2rem;
  display: flex;
  align-items: center;
  gap: .25rem;
  flex-wrap: wrap;
  box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.sdg-nav-label {
  font-size: .6rem;
  font-weight: 800;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--sdg-muted);
  margin-right: .5rem;
  white-space: nowrap;
}
.sdg-nav a {
  padding: .3rem .65rem;
  border-radius: 20px;
  font-size: .75rem;
  color: var(--sdg-dim);
  text-decoration: none;
  transition: background .12s, color .12s;
  white-space: nowrap;
}
.sdg-nav a:hover {
  background: var(--sdg-glow);
  color: var(--sdg-accent);
}

/* ── Hero ─────────────────────────────────────────────────────────── */
.sdg-hero {
  padding-bottom: 2rem;
  border-bottom: 1px solid var(--sdg-border);
  margin-bottom: 2.5rem;
}
.sdg-eyebrow {
  font-size: .62rem;
  font-weight: 800;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: var(--sdg-accent);
  margin-bottom: .6rem;
}
.sdg-hero h1 {
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -.03em;
  line-height: 1.2;
  color: var(--sdg-text);
  margin-bottom: .75rem;
  text-wrap: balance;
}
.sdg-hero .sdg-lead {
  font-size: .95rem;
  color: var(--sdg-dim);
  max-width: 560px;
  line-height: 1.7;
  margin-bottom: 1.25rem;
}
.sdg-pills { display: flex; gap: .5rem; flex-wrap: wrap; }
.sdg-pill {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .25rem .65rem;
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 600;
}
.sdg-pill-s { background: var(--sdg-s-bg); color: var(--sdg-s-fg); border: 1px solid rgba(96,165,250,.2); }
.sdg-pill-d { background: var(--sdg-d-bg); color: var(--sdg-d-fg); border: 1px solid rgba(52,211,153,.2); }
.sdg-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

/* ── Section ──────────────────────────────────────────────────────── */
.sdg-section { margin-bottom: 2.75rem; scroll-margin-top: 5rem; }
.sdg-section-hd {
  display: flex;
  align-items: center;
  gap: .6rem;
  margin-bottom: 1rem;
}
.sdg-section-hd .sdg-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  background: var(--sdg-accent);
  color: #fff;
  font-size: .6rem;
  font-weight: 800;
  border-radius: 50%;
  flex-shrink: 0;
}
.sdg-section-hd h2 {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--sdg-text);
  letter-spacing: -.02em;
  margin: 0;
}
.sdg-section h3 {
  font-size: .9rem;
  font-weight: 700;
  color: var(--sdg-text);
  margin: 1.5rem 0 .5rem;
}
.sdg-section p {
  font-size: .9rem;
  color: var(--sdg-dim);
  margin-bottom: .8rem;
}

/* ── Pills / badges ───────────────────────────────────────────────── */
.sdg-badge {
  display: inline-block;
  font-size: .58rem;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
  padding: .1em .42em;
  border-radius: 3px;
}
.sdg-badge-s { background: var(--sdg-s-bg); color: var(--sdg-s-fg); }
.sdg-badge-d { background: var(--sdg-d-bg); color: var(--sdg-d-fg); }

/* ── File tree ────────────────────────────────────────────────────── */
.sdg-tree {
  background: var(--sdg-code);
  border: 1px solid var(--sdg-border);
  border-radius: var(--sdg-radius);
  padding: 1.1rem 1.4rem;
  font-family: 'JetBrains Mono','Fira Code','Cascadia Code',ui-monospace,monospace;
  font-size: .77rem;
  line-height: 2;
  overflow-x: auto;
  margin: 1rem 0 1.5rem;
  color: #94a3b8;
}
.sdg-tree .ft-dir { color: #e2e8f0; font-weight: 600; }
.sdg-tree .ft-s   { color: #60a5fa; }
.sdg-tree .ft-d   { color: #34d399; }
.sdg-tree .ft-tag {
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .08em .38em;
  border-radius: 3px;
  margin-left: .5rem;
  vertical-align: middle;
}
.sdg-tree .ft-ts { background: rgba(96,165,250,.15); color: #60a5fa; }
.sdg-tree .ft-td { background: rgba(52,211,153,.15); color: #34d399; }

/* ── Code block ───────────────────────────────────────────────────── */
.sdg-cb {
  background: var(--sdg-code);
  border: 1px solid var(--sdg-border);
  border-radius: var(--sdg-radius);
  overflow: hidden;
  margin: .9rem 0;
}
.sdg-cb-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .4rem .9rem;
  background: rgba(255,255,255,.025);
  border-bottom: 1px solid var(--sdg-border);
  font-family: 'JetBrains Mono','Fira Code',monospace;
  font-size: .65rem;
  color: #64748b;
}
.sdg-cb pre {
  padding: 1rem 1.4rem;
  font-family: 'JetBrains Mono','Fira Code','Cascadia Code',ui-monospace,monospace;
  font-size: .76rem;
  line-height: 1.8;
  overflow-x: auto;
  color: #cbd5e1;
  margin: 0;
}
.sdg-cb .kw  { color: #c792ea; }
.sdg-cb .cl  { color: #82aaff; }
.sdg-cb .fn  { color: #89ddff; }
.sdg-cb .st  { color: #c3e88d; }
.sdg-cb .cm  { color: #4a5568; }
.sdg-cb .at  { color: #f78c6c; }
.sdg-cb .hl  { display:block; background:rgba(129,140,248,.1); margin:0 -1.4rem; padding:0 1.4rem; }

/* ── Steps ────────────────────────────────────────────────────────── */
.sdg-steps { list-style: none; margin: 1rem 0; counter-reset: sdgs; padding: 0; }
.sdg-steps li {
  counter-increment: sdgs;
  display: flex;
  gap: .8rem;
  margin-bottom: 1.1rem;
}
.sdg-steps li::before {
  content: counter(sdgs);
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 1.5rem;
  height: 1.5rem;
  background: var(--sdg-glow);
  border: 1px solid var(--sdg-accent);
  color: var(--sdg-accent);
  font-size: .65rem;
  font-weight: 800;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: .15rem;
}
.sdg-step-title { font-weight: 600; color: var(--sdg-text); font-size: .9rem; margin-bottom: .18rem; }
.sdg-steps li p { margin: 0; font-size: .85rem; }

/* ── Callout ──────────────────────────────────────────────────────── */
.sdg-callout {
  display: flex;
  gap: .7rem;
  padding: .8rem .95rem;
  border-radius: var(--sdg-radius);
  margin: 1rem 0;
  font-size: .86rem;
}
.sdg-callout p { margin: 0; color: var(--sdg-dim); }
.sdg-ci { font-size: .95rem; flex-shrink: 0; }
.sdg-callout-info    { background: rgba(129,140,248,.06); border: 1px solid rgba(129,140,248,.2); }
.sdg-callout-warn    { background: rgba(251,191,36,.05);  border: 1px solid rgba(251,191,36,.2); }
.sdg-callout-success { background: rgba(52,211,153,.05);  border: 1px solid rgba(52,211,153,.2); }

/* ── Table ────────────────────────────────────────────────────────── */
.sdg-tbl-wrap { overflow-x: auto; margin: 1rem 0; border-radius: var(--sdg-radius); border: 1px solid var(--sdg-border); }
.sdg-tbl { width: 100%; border-collapse: collapse; font-size: .82rem; }
.sdg-tbl th {
  text-align: left;
  padding: .55rem .9rem;
  background: rgba(128,128,128,.04);
  color: var(--sdg-muted);
  font-size: .62rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  border-bottom: 1px solid var(--sdg-border);
  white-space: nowrap;
}
.sdg-tbl td { padding: .6rem .9rem; border-bottom: 1px solid var(--sdg-border); vertical-align: middle; }
.sdg-tbl tr:last-child td { border-bottom: none; }
.sdg-tbl .td-f { font-family: 'JetBrains Mono','Fira Code',monospace; font-size: .73rem; color: var(--sdg-dim); }
.sdg-tbl .td-s { color: var(--sdg-s-fg); font-size: .78rem; }
.sdg-tbl .td-d { color: var(--sdg-d-fg); font-size: .78rem; }
.sdg-tbl .td-safe { color: var(--sdg-success); font-weight: 700; font-size: .78rem; }
.sdg-tbl .td-regen { color: var(--sdg-danger); font-weight: 700; font-size: .78rem; }
.sdg-tbl .td-note { font-size: .8rem; color: var(--sdg-dim); }

/* ── Cards ────────────────────────────────────────────────────────── */
.sdg-cards { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; margin: 1rem 0; }
.sdg-card {
  background: var(--sdg-card);
  border: 1px solid var(--sdg-border);
  border-radius: var(--sdg-radius);
  padding: .95rem 1.05rem;
}
.sdg-card h4 {
  font-size: .67rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  margin-bottom: .65rem;
}
.sdg-card-s h4 { color: var(--sdg-s-fg); }
.sdg-card-d h4 { color: var(--sdg-d-fg); }
.sdg-card ul { list-style: none; padding: 0; margin: 0; font-size: .77rem; color: var(--sdg-dim); line-height: 2.1; font-family: 'JetBrains Mono','Fira Code',monospace; }

/* ── Inline code ──────────────────────────────────────────────────── */
.sdg code {
  font-family: 'JetBrains Mono','Fira Code',monospace;
  font-size: .8em;
  background: var(--sdg-glow);
  color: var(--sdg-accent);
  padding: .1em .32em;
  border-radius: 4px;
}

/* ── Divider ──────────────────────────────────────────────────────── */
.sdg-hr { border: none; border-top: 1px solid var(--sdg-border); margin: 2.25rem 0; }

/* ── Responsive ───────────────────────────────────────────────────── */
@media (max-width: 640px) {
  .sdg-hero h1 { font-size: 1.35rem; }
  .sdg-cards { grid-template-columns: 1fr; }
  .sdg-nav { gap: .15rem; }
  .sdg-nav a { font-size: .7rem; padding: .25rem .5rem; }
}
</style>

{{-- ── Section nav ────────────────────────────────────────────── --}}
<div class="sdg-nav">
  <span class="sdg-nav-label">Jump to</span>
  <a href="#sdg-overview">Overview</a>
  <a href="#sdg-structure">Directory Structure</a>
  <a href="#sdg-json">Form &amp; Table JSON</a>
  <a href="#sdg-model">Model Extension</a>
  <a href="#sdg-rebuild">Rebuild Safety</a>
  <a href="#sdg-migrate">Migrating Old Modules</a>
  <a href="#sdg-dropdowns">Dropdown DOMs</a>
  <a href="#sdg-quickref">Quick Reference</a>
</div>

{{-- ── Hero ─────────────────────────────────────────────────────── --}}
<div class="sdg-hero">
  <div class="sdg-eyebrow">SSI Studio — Developer Guide</div>
  <h1>Customize Modules Without Getting Overridden</h1>
  <p class="sdg-lead">
    Studio generates and maintains files for every deployed module.
    This guide explains which files belong to Studio, which belong to you,
    and exactly how to add custom logic that survives every Repair &amp; Rebuild.
  </p>
  <div class="sdg-pills">
    <span class="sdg-pill sdg-pill-s"><span class="sdg-dot"></span>Studio-owned — regenerated on rebuild</span>
    <span class="sdg-pill sdg-pill-d"><span class="sdg-dot"></span>Developer-owned — never touched</span>
  </div>
</div>

{{-- ── 1. Overview ─────────────────────────────────────────────── --}}
<section class="sdg-section" id="sdg-overview">
  <div class="sdg-section-hd"><span class="sdg-num">1</span><h2>How the Two-Tier System Works</h2></div>
  <p>Every deployed module has two ownership tiers. Studio writes to its own tier freely — this is how field changes, relationship updates, and layout changes get applied. Your tier is never read or written by any Studio operation.</p>
  <p>The runtime always checks the developer tier first. If you've placed a file there, it takes priority. If not, Studio's generated version is used as the fallback.</p>
  <div class="sdg-callout sdg-callout-info">
    <span class="sdg-ci">💡</span>
    <p>You don't need to duplicate everything — only copy the specific file you want to customise. Unmodified views continue loading from Studio's generated copies.</p>
  </div>
</section>

<div class="sdg-hr"></div>

{{-- ── 2. Directory Structure ───────────────────────────────────── --}}
<section class="sdg-section" id="sdg-structure">
  <div class="sdg-section-hd"><span class="sdg-num">2</span><h2>Directory Structure</h2></div>
  <p>Using a <code>Leads</code> module as an example. The same pattern applies to every module.</p>
  <div class="sdg-tree">
<span class="ft-dir">app/</span>
├── <span class="ft-dir">Models/</span>
│   ├── <span class="ft-dir">Studio/</span>
│   │   └── <span class="ft-s">BaseLeads.php</span><span class="ft-tag ft-ts">studio</span>   ← table, fillable, casts, relationships
│   └── <span class="ft-d">Leads.php</span><span class="ft-tag ft-td">yours</span>               ← add your custom code here
│
└── <span class="ft-dir">Filament/Resources/Leads/</span>
    ├── <span class="ft-dir">Schemas/</span>
    │   ├── <span class="ft-s">createView.json</span><span class="ft-tag ft-ts">studio</span>
    │   ├── <span class="ft-s">editView.json</span><span class="ft-tag ft-ts">studio</span>
    │   ├── <span class="ft-s">detailView.json</span><span class="ft-tag ft-ts">studio</span>
    │   └── <span class="ft-s">default.json</span><span class="ft-tag ft-ts">studio</span>
    ├── <span class="ft-dir">Tables/</span>
    │   └── <span class="ft-s">listView.json</span><span class="ft-tag ft-ts">studio</span>
    ├── <span class="ft-dir">CustomSchemas/</span>                    ← YOUR form override zone
    │   └── <span class="ft-d">createView.json</span><span class="ft-tag ft-td">yours</span>     ← runtime loads this first
    └── <span class="ft-dir">CustomTables/</span>                     ← YOUR table override zone
        └── <span class="ft-d">listView.json</span><span class="ft-tag ft-td">yours</span>        ← runtime loads this first</div>
  <div class="sdg-callout sdg-callout-success">
    <span class="sdg-ci">✅</span>
    <p><code>CustomSchemas/</code> and <code>CustomTables/</code> are created automatically on first deploy. You'll find a <code>README.md</code> inside each one explaining the pattern.</p>
  </div>
</section>

<div class="sdg-hr"></div>

{{-- ── 3. JSON Customization ────────────────────────────────────── --}}
<section class="sdg-section" id="sdg-json">
  <div class="sdg-section-hd"><span class="sdg-num">3</span><h2>Customising Forms &amp; Tables (JSON)</h2></div>
  <p>Form layouts (create, edit, detail) and the list table are defined in JSON files. Studio regenerates the copies in <code>Schemas/</code> and <code>Tables/</code> on every Repair &amp; Rebuild. To override any of them, place your version in <code>CustomSchemas/</code> or <code>CustomTables/</code>.</p>

  <h3>Step-by-step</h3>
  <ol class="sdg-steps">
    <li>
      <div>
        <div class="sdg-step-title">Copy the file you want to customise</div>
        <p>Copy from <code>Schemas/</code> (or <code>Tables/</code>) into the matching <code>Custom*</code> folder.</p>
      </div>
    </li>
    <li>
      <div>
        <div class="sdg-step-title">Edit your copy freely</div>
        <p>Add fields, reorder sections, change column widths, add filters — anything the JSON schema supports.</p>
      </div>
    </li>
    <li>
      <div>
        <div class="sdg-step-title">Deploy or Repair &amp; Rebuild as normal</div>
        <p>Studio updates its copy in <code>Schemas/</code>. Your copy in <code>CustomSchemas/</code> is untouched. At runtime, your version wins.</p>
      </div>
    </li>
  </ol>

  <h3>Example — custom create form</h3>
  <div class="sdg-cb">
    <div class="sdg-cb-head">
      <span>app/Filament/Resources/Leads/CustomSchemas/createView.json</span>
      <span class="sdg-badge sdg-badge-d">yours</span>
    </div>
    <pre>{
  <span class="st">"components"</span>: [
    {
      <span class="st">"type"</span>: <span class="st">"section"</span>,
      <span class="st">"label"</span>: <span class="st">"Lead Information"</span>,
      <span class="st">"columns"</span>: <span class="at">2</span>,
      <span class="st">"components"</span>: [
        { <span class="st">"key"</span>: <span class="st">"name"</span> },
        { <span class="st">"key"</span>: <span class="st">"email"</span> },
        { <span class="st">"key"</span>: <span class="st">"phone"</span> },
        { <span class="st">"key"</span>: <span class="st">"source"</span>, <span class="st">"columnSpan"</span>: <span class="at">2</span> }
      ]
    },
    {
      <span class="st">"type"</span>: <span class="st">"section"</span>,
      <span class="st">"label"</span>: <span class="st">"Custom Notes"</span>,  <span class="cm">// ← added by developer</span>
      <span class="st">"components"</span>: [
        { <span class="st">"key"</span>: <span class="st">"internal_notes"</span> }
      ]
    }
  ]
}</pre>
  </div>

  <h3>Supported override files</h3>
  <div class="sdg-tbl-wrap">
    <table class="sdg-tbl">
      <thead><tr><th>File</th><th>What it controls</th><th>Your override path</th></tr></thead>
      <tbody>
        <tr><td class="td-f">createView.json</td><td class="td-note">Create record form layout</td><td class="td-f td-d">CustomSchemas/createView.json</td></tr>
        <tr><td class="td-f">editView.json</td><td class="td-note">Edit record form layout</td><td class="td-f td-d">CustomSchemas/editView.json</td></tr>
        <tr><td class="td-f">detailView.json</td><td class="td-note">View / detail layout</td><td class="td-f td-d">CustomSchemas/detailView.json</td></tr>
        <tr><td class="td-f">default.json</td><td class="td-note">Fallback form layout</td><td class="td-f td-d">CustomSchemas/default.json</td></tr>
        <tr><td class="td-f">listView.json</td><td class="td-note">Table columns, filters, actions</td><td class="td-f td-d">CustomTables/listView.json</td></tr>
      </tbody>
    </table>
  </div>
</section>

<div class="sdg-hr"></div>

{{-- ── 4. Model Extension ───────────────────────────────────────── --}}
<section class="sdg-section" id="sdg-model">
  <div class="sdg-section-hd"><span class="sdg-num">4</span><h2>Customising the Model (PHP)</h2></div>
  <p>The Eloquent model is split into two classes. Studio owns the base class and regenerates it on every Repair &amp; Rebuild. Your developer file extends it and is generated exactly once — never overwritten again.</p>

  <h3>What Studio manages — BaseLeads.php</h3>
  <div class="sdg-cb">
    <div class="sdg-cb-head">
      <span>app/Models/Studio/BaseLeads.php</span>
      <span class="sdg-badge sdg-badge-s">studio</span>
    </div>
    <pre><span class="kw">abstract class</span> <span class="cl">BaseLeads</span> <span class="kw">extends</span> <span class="cl">Model</span>
{
    <span class="kw">use</span> <span class="cl">HasCreatedBy</span>, <span class="cl">ModuleHookTrait</span>;

    <span class="kw">protected</span> <span class="at">$table</span>   = <span class="st">'leads'</span>;
    <span class="kw">protected</span> <span class="at">$guarded</span> = [<span class="st">'id'</span>];

    <span class="kw">protected</span> <span class="at">$casts</span> = [        <span class="cm">// ← regenerated from field definitions</span>
        <span class="st">'tags'</span>      => <span class="st">'array'</span>,
        <span class="st">'documents'</span> => <span class="st">'array'</span>,
    ];

    <span class="kw">public function</span> <span class="fn">owner</span>()   <span class="cm">// ← relationships from Studio config</span>
    {
        <span class="kw">return</span> <span class="at">$this</span>-><span class="fn">belongsTo</span>(\<span class="cl">App\Models\User</span>::class);
    }
}</pre>
  </div>

  <h3>What you control — Leads.php</h3>
  <div class="sdg-cb">
    <div class="sdg-cb-head">
      <span>app/Models/Leads.php</span>
      <span class="sdg-badge sdg-badge-d">yours</span>
    </div>
    <pre><span class="kw">class</span> <span class="cl">Leads</span> <span class="kw">extends</span> <span class="cl">BaseLeads</span>      <span class="cm">// inherits everything above</span>
{
<span class="hl">    <span class="cm">// Custom scopes</span>
    <span class="kw">public function</span> <span class="fn">scopeHot</span>(<span class="at">$query</span>)
    {
        <span class="kw">return</span> <span class="at">$query</span>-><span class="fn">where</span>(<span class="st">'score'</span>, <span class="st">'>'</span>, <span class="at">80</span>);
    }</span>

<span class="hl">    <span class="cm">// Custom accessors</span>
    <span class="kw">protected function</span> <span class="fn">fullName</span>(): <span class="cl">Attribute</span>
    {
        <span class="kw">return</span> <span class="cl">Attribute</span>::<span class="fn">get</span>(<span class="fn">fn</span>() => <span class="at">$this</span>->first_name . <span class="st">' '</span> . <span class="at">$this</span>->last_name);
    }</span>

<span class="hl">    <span class="cm">// Extra relationships</span>
    <span class="kw">public function</span> <span class="fn">activities</span>()
    {
        <span class="kw">return</span> <span class="at">$this</span>-><span class="fn">hasMany</span>(\<span class="cl">App\Models\Activity</span>::class);
    }</span>
}</pre>
  </div>

  <div class="sdg-cards">
    <div class="sdg-card sdg-card-d">
      <h4>Always safe to add</h4>
      <ul>
        <li>Custom scopes (<code>scopeActive()</code>)</li>
        <li>Accessors &amp; mutators</li>
        <li>Extra relationships</li>
        <li>boot / booted methods</li>
        <li>Observer registration</li>
        <li>Custom casts</li>
      </ul>
    </div>
    <div class="sdg-card sdg-card-s">
      <h4>Don't redeclare these</h4>
      <ul>
        <li><code>$table</code> (set in base)</li>
        <li><code>$guarded</code> (set in base)</li>
        <li>Studio relationships</li>
        <li>UUID boot logic</li>
        <li><code>$casts</code> base entries</li>
      </ul>
    </div>
  </div>
</section>

<div class="sdg-hr"></div>

{{-- ── 5. Rebuild Safety ────────────────────────────────────────── --}}
<section class="sdg-section" id="sdg-rebuild">
  <div class="sdg-section-hd"><span class="sdg-num">5</span><h2>What Survives a Repair &amp; Rebuild</h2></div>
  <p>Every Repair &amp; Rebuild touches exactly the Studio-owned files listed below — nothing else.</p>
  <div class="sdg-tbl-wrap">
    <table class="sdg-tbl">
      <thead><tr><th>File</th><th>Owner</th><th>On Rebuild</th></tr></thead>
      <tbody>
        <tr><td class="td-f">Models/Studio/Base{Model}.php</td><td class="td-s">Studio</td><td class="td-regen">Regenerated</td></tr>
        <tr><td class="td-f">Schemas/createView.json</td><td class="td-s">Studio</td><td class="td-regen">Regenerated</td></tr>
        <tr><td class="td-f">Schemas/editView.json</td><td class="td-s">Studio</td><td class="td-regen">Regenerated</td></tr>
        <tr><td class="td-f">Schemas/detailView.json</td><td class="td-s">Studio</td><td class="td-regen">Regenerated</td></tr>
        <tr><td class="td-f">Schemas/default.json</td><td class="td-s">Studio</td><td class="td-regen">Regenerated</td></tr>
        <tr><td class="td-f">Tables/listView.json</td><td class="td-s">Studio</td><td class="td-regen">Regenerated</td></tr>
        <tr><td class="td-f">Schemas/{Model}Form.php</td><td class="td-s">Studio (glue)</td><td class="td-regen">Regenerated</td></tr>
        <tr><td class="td-f">Tables/{Resource}Table.php</td><td class="td-s">Studio (glue)</td><td class="td-regen">Regenerated</td></tr>
        <tr style="background:rgba(52,211,153,.04)"><td class="td-f">Models/{Model}.php</td><td class="td-d">Developer</td><td class="td-safe">Preserved ✓</td></tr>
        <tr style="background:rgba(52,211,153,.04)"><td class="td-f">CustomSchemas/*.json</td><td class="td-d">Developer</td><td class="td-safe">Preserved ✓</td></tr>
        <tr style="background:rgba(52,211,153,.04)"><td class="td-f">CustomTables/listView.json</td><td class="td-d">Developer</td><td class="td-safe">Preserved ✓</td></tr>
        <tr style="background:rgba(52,211,153,.04)"><td class="td-f">Pages/Create{Model}.php</td><td class="td-d">Developer</td><td class="td-safe">Preserved ✓</td></tr>
        <tr style="background:rgba(52,211,153,.04)"><td class="td-f">Pages/Edit{Model}.php</td><td class="td-d">Developer</td><td class="td-safe">Preserved ✓</td></tr>
        <tr style="background:rgba(52,211,153,.04)"><td class="td-f">Pages/List{Resource}.php</td><td class="td-d">Developer</td><td class="td-safe">Preserved ✓</td></tr>
        <tr style="background:rgba(52,211,153,.04)"><td class="td-f">Pages/View{Model}.php</td><td class="td-d">Developer</td><td class="td-safe">Preserved ✓</td></tr>
        <tr style="background:rgba(52,211,153,.04)"><td class="td-f">{Model}Resource.php</td><td class="td-d">Developer</td><td class="td-safe">Preserved ✓</td></tr>
      </tbody>
    </table>
  </div>
  <div class="sdg-callout sdg-callout-warn">
    <span class="sdg-ci">⚠️</span>
    <p><strong>Do not customise</strong> <code>*Form.php</code> or <code>*Table.php</code> (the glue files). These are Studio-owned thin wrappers regenerated on every rebuild. Place your changes in <code>CustomSchemas/</code> or <code>CustomTables/</code> JSON instead.</p>
  </div>
</section>

<div class="sdg-hr"></div>

{{-- ── 6. Migration ─────────────────────────────────────────────── --}}
<section class="sdg-section" id="sdg-migrate">
  <div class="sdg-section-hd"><span class="sdg-num">6</span><h2>Migrating an Existing Module</h2></div>
  <p>Modules deployed before this system was introduced have a single-file <code>Leads.php</code> that extends <code>Model</code> directly with Studio region markers inside. These continue to work — the legacy region sync still runs on them automatically.</p>
  <p>To fully opt in to the new pattern, run one Repair &amp; Rebuild first (this creates <code>Studio/BaseLeads.php</code>), then make three changes to your model:</p>

  <h3>Before — old single-file style</h3>
  <div class="sdg-cb">
    <div class="sdg-cb-head"><span>app/Models/Leads.php — old style</span></div>
    <pre><span class="kw">class</span> <span class="cl">Leads</span> <span class="kw">extends</span> <span class="cl">Model</span>   <span class="cm">// ← change this</span>
{
    <span class="kw">use</span> <span class="cl">HasCreatedBy</span>, <span class="cl">ModuleHookTrait</span>;
    <span class="kw">protected</span> <span class="at">$table</span>   = <span class="st">'leads'</span>;  <span class="cm">// ← delete</span>
    <span class="kw">protected</span> <span class="at">$guarded</span> = [<span class="st">'id'</span>];   <span class="cm">// ← delete</span>
    <span class="kw">protected</span> <span class="at">$casts</span> = [...];       <span class="cm">// ← delete</span>
    <span class="cm">// region:studio-route-key ...</span>     <span class="cm">// ← delete entire region block</span>
    <span class="cm">// region:studio-relationships ...</span> <span class="cm">// ← delete entire region block</span>

    <span class="cm">// your custom code stays</span>
    <span class="kw">public function</span> <span class="fn">scopeHot</span>(<span class="at">$q</span>) { ... }
}</pre>
  </div>

  <h3>After — new style (3 changes)</h3>
  <div class="sdg-cb">
    <div class="sdg-cb-head"><span>app/Models/Leads.php — new style</span><span class="sdg-badge sdg-badge-d">yours</span></div>
    <pre><span class="hl"><span class="kw">use</span> <span class="cl">App\Models\Studio\BaseLeads</span>;     </span><span class="cm">// 1. add use statement</span>

<span class="kw">class</span> <span class="cl">Leads</span> <span class="kw">extends</span> <span class="hl"><span class="cl">BaseLeads</span>          </span><span class="cm">// 2. extend BaseLeads</span>
{
    <span class="cm">// 3. only your custom code remains</span>
    <span class="kw">public function</span> <span class="fn">scopeHot</span>(<span class="at">$q</span>)
    {
        <span class="kw">return</span> <span class="at">$q</span>-><span class="fn">where</span>(<span class="st">'score'</span>, <span class="st">'>'</span>, <span class="at">80</span>);
    }
}</pre>
  </div>
  <div class="sdg-callout sdg-callout-success">
    <span class="sdg-ci">✅</span>
    <p>After this change, all Studio-managed properties and relationships come from <code>BaseLeads</code>. Your custom code is the only thing in your file — with zero risk of being overwritten.</p>
  </div>
</section>

<div class="sdg-hr"></div>

{{-- ── 7. Dropdown DOMs ─────────────────────────────────────────── --}}
<section class="sdg-section" id="sdg-dropdowns">
  <div class="sdg-section-hd"><span class="sdg-num">7</span><h2>Dropdown DOM Files</h2></div>
  <p>Dropdown option lists ("DOMs") live in two separate files. Studio-internal options are version-controlled in PHP config. Module-level options your application creates at runtime live in a writable JSON file.</p>

  <div class="sdg-tbl-wrap">
    <table class="sdg-tbl">
      <thead><tr><th>File</th><th>Owner</th><th>Contains</th><th>Writable at runtime?</th></tr></thead>
      <tbody>
        <tr>
          <td class="td-f">config/studio_doms.php</td>
          <td class="td-s">Studio</td>
          <td class="td-note"><code>field_type_dom</code>, <code>layout_type_dom</code>, <code>moudle_icons_dom</code>, <code>visibility_mode_dom</code>, <code>condition_logic_dom</code>, <code>relationship_type_dom</code>, <code>filter_type_dom</code>, <code>operator_dom</code></td>
          <td class="td-note">No — edit the PHP file directly</td>
        </tr>
        <tr style="background:rgba(52,211,153,.04)">
          <td class="td-f">storage/app/SSI/Dropdowns/app_doms.json</td>
          <td class="td-d">Developer / App</td>
          <td class="td-note">User-created groups: <code>status_dom</code>, <code>gender_dom</code>, and any DOM generated when a module field is deployed</td>
          <td class="td-note">Yes — written by <code>DropdownHandler</code></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="sdg-callout sdg-callout-info">
    <span class="sdg-ci">💡</span>
    <p><code>DropdownHandler::get('field_type_dom')</code> checks <code>app_doms.json</code> first, then falls back to <code>studio_doms.php</code> — callers never need to know which file a group came from. All write methods (<code>set</code>, <code>createGroup</code>, <code>deleteGroup</code>) always target <code>app_doms.json</code> only.</p>
  </div>

  <h3>Reading a dropdown in JSON (form or table)</h3>
  <div class="sdg-cb">
    <div class="sdg-cb-head"><span>Schemas/createView.json — badge column using a Studio DOM</span></div>
    <pre>{
  <span class="st">"type"</span>: <span class="st">"badge"</span>,
  <span class="st">"name"</span>: <span class="st">"status"</span>,
  <span class="st">"dropdown"</span>: <span class="st">"status_dom"</span>    <span class="cm">// ← key from app_doms.json</span>
}</pre>
  </div>
  <div class="sdg-cb">
    <div class="sdg-cb-head"><span>Tables/listView.json — filter using a Studio DOM via helper</span></div>
    <pre>{
  <span class="st">"type"</span>: <span class="st">"select"</span>,
  <span class="st">"name"</span>: <span class="st">"type"</span>,
  <span class="st">"options_source"</span>: <span class="st">"helper"</span>,
  <span class="st">"helper_class"</span>: <span class="st">"App\\Helpers\\Studio\\DropdownHandler"</span>,
  <span class="st">"helper_method"</span>: <span class="st">"get"</span>,
  <span class="st">"helper_params"</span>: [<span class="st">"field_type_dom"</span>]    <span class="cm">// ← resolved from studio_doms.php</span>
}</pre>
  </div>

  <h3>Creating a new app DOM in PHP</h3>
  <div class="sdg-cb">
    <div class="sdg-cb-head"><span>PHP — add or update a group in app_doms.json</span></div>
    <pre><span class="kw">use</span> <span class="cl">App\Helpers\Studio\DropdownHandler</span>;

<span class="cm">// Create a new group (module + field name → "{module}_{field}_dom")</span>
<span class="cl">DropdownHandler</span>::<span class="fn">createGroup</span>(<span class="st">'lead'</span>, <span class="st">'priority'</span>, [
    [<span class="st">'key'</span> => <span class="st">'high'</span>,   <span class="st">'value'</span> => <span class="st">'High'</span>],
    [<span class="st">'key'</span> => <span class="st">'medium'</span>, <span class="st">'value'</span> => <span class="st">'Medium'</span>],
    [<span class="st">'key'</span> => <span class="st">'low'</span>,    <span class="st">'value'</span> => <span class="st">'Low'</span>],
]);
<span class="cm">// Creates "lead_priority_dom" in app_doms.json</span>

<span class="cm">// Add a single option to an existing group</span>
<span class="cl">DropdownHandler</span>::<span class="fn">set</span>(<span class="st">'status_dom'</span>, <span class="st">'archived'</span>, <span class="st">'Archived'</span>);

<span class="cm">// Read (always merges both files transparently)</span>
<span class="at">$options</span> = <span class="cl">DropdownHandler</span>::<span class="fn">get</span>(<span class="st">'status_dom'</span>);</pre>
  </div>

  <h3>Extending a Studio DOM</h3>
  <p>Studio DOMs in <code>config/studio_doms.php</code> are read-only at runtime. If you need to add an option to one (e.g. a custom field type), edit <code>config/studio_doms.php</code> directly and commit the change. Alternatively, add the same key to <code>app_doms.json</code> — the user file wins on collision.</p>

  <div class="sdg-callout sdg-callout-warn">
    <span class="sdg-ci">⚠️</span>
    <p>Never call <code>DropdownHandler::createGroup()</code> or <code>set()</code> with a Studio DOM key (<code>field_type_dom</code>, <code>layout_type_dom</code>, etc.) in application code. Those groups belong in <code>studio_doms.php</code>. Use <code>DropdownHandler::isStudioDom($key)</code> to check before writing.</p>
  </div>
</section>

<div class="sdg-hr"></div>

{{-- ── 8. Quick Reference ───────────────────────────────────────── --}}
<section class="sdg-section" id="sdg-quickref">
  <div class="sdg-section-hd"><span class="sdg-num">8</span><h2>Quick Reference</h2></div>
  <div class="sdg-cards">
    <div class="sdg-card sdg-card-s">
      <h4>Studio owns — auto-regenerated</h4>
      <ul>
        <li>Models/Studio/Base{Model}.php</li>
        <li>Schemas/createView.json</li>
        <li>Schemas/editView.json</li>
        <li>Schemas/detailView.json</li>
        <li>Schemas/default.json</li>
        <li>Tables/listView.json</li>
        <li>Schemas/{Model}Form.php</li>
        <li>Tables/{Resource}Table.php</li>
      </ul>
    </div>
    <div class="sdg-card sdg-card-d">
      <h4>You own — never overwritten</h4>
      <ul>
        <li>Models/{Model}.php</li>
        <li>CustomSchemas/createView.json</li>
        <li>CustomSchemas/editView.json</li>
        <li>CustomSchemas/detailView.json</li>
        <li>CustomSchemas/default.json</li>
        <li>CustomTables/listView.json</li>
        <li>Pages/Create{Model}.php</li>
        <li>Pages/Edit{Model}.php</li>
        <li>Pages/List{Resource}.php</li>
        <li>Pages/View{Model}.php</li>
      </ul>
    </div>
  </div>
  <div class="sdg-callout sdg-callout-info" style="margin-top:1.25rem">
    <span class="sdg-ci">📌</span>
    <p>Put JSON overrides in <code>Custom*</code> folders. Put PHP code in <code>Models/{Model}.php</code> by extending <code>Base{Model}</code>. Studio will never touch either.</p>
  </div>
</section>

</div>{{-- .sdg --}}
</x-filament-panels::page>
