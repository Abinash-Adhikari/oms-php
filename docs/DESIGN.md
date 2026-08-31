# SB-Tech — Design System & UX Specification

> **Author:** Design-Engineering Lead (20-yr full-stack veteran)
> **Status:** Living spec — the code is the source of truth; this mirrors it.
> **Scope:** Public website + Admin OMS panel.

---

## 1. Visual Language

### 1.1 Color System

Two layers: a **theme layer** (light/dark + accent, persisted to `localStorage`)
and a **semantic layer** (roles map to specific colors).

#### Theme Tokens (`assets/css/theme-variables.css`)

```css
:root {
  /* Light defaults */
  --bg: #ffffff;        /* canvas */
  --bg-alt: #f8f9fa;    /* cards, sections */
  --text: #212529;      /* primary copy */
  --text-muted: #6c757d;
  --border: rgba(0,0,0,.065);
  --accent: #2563eb;    /* brand blue — used in --accent-... scale */
  --radius: .5rem;
  --radius-full: 9999px;
}
[data-mode="dark"] {
  --bg: #0f1117;
  --bg-alt: #1a1e27;
  --text: #e4e6eb;
  --text-muted: #9ca3af;
  --border: rgba(255,255,255,.065);
}
[data-accent="blue"|"teal"|"amber"|"rose"] {
  --accent: <palette>;          /* 6 accent families supported */
  --accent-hover: ...;          /* auto-derived: 10% darker via CSS filter */
  --accent-soft: ...;           /* 8% tint for badges/ghost btn */
}
```

**Theme persistence:** both the website and admin panel write to the *same*
`localStorage` keys (`app_color_mode`, `app_accent`) — a user switching to
dark mode on the site stays in dark mode when they hit `/admin/login.php`.
This is intentional: one identity, one preference surface.

**Theme boot (inline, blocking):**
Both `website/includes/header.php` and `admin/includes/theme-boot.php` inline
a `<script>` as the **first child of `<head>`**. This prevents a flash of light
mode on dark-first loads. No framework JS is loaded before this runs.

#### Semantic Colors (admin, mapped from bootstrap contextual classes)

| Semantic meaning | Class | Color |
|---|---|---|
| Brand / primary | `btn-primary`, `.text-primary` | `--accent` |
| Success | `alert-success`, `badge-success` | `#16a34a` (emerald) |
| Warning | `alert-warning`, KPI `warning` | `#d97706` (amber) |
| Danger | `alert-danger`, KPI `danger` | `#dc2626` (rose) |
| Info | `alert-info`, KPI `info` | `#0ea5e9` (sky) |
| Muted | `text-muted` | `--text-muted` |

These map directly to the **KPI card `color` field** in
`admin/modules/dashboard/home.php`:
```php
'pending_leaves' => ['color' => 'warning', …],
'overdue_tasks'   => ['color' => 'danger',  …],
```
→ renders `<div class="small-box bg-<?= e($card['color']) ?>">` (AdminLTE pattern).
The semantic class is the contract.

### 1.2 Typography

```
font-stack-display: 'Poppins', sans-serif   — headings, nav brand, hero
font-stack-body:    'Open Sans', sans-serif  — all body copy, forms, tables
font-stack-mono:    'JetBrains Mono', monospace — code, audit-log old/new diffs
```

Loaded from **Google Fonts** via `preconnect` + `dns-prefetch`. CDN-first:
the host (`$7 LAMP`) doesn't cache fonts, but Google Fonts has exceptional
edge coverage in the subcontinent.

**Hierarchy:**
- `h1` 2.5rem / 700 (Poppins) — hero headline, page title
- `h2` 1.75rem / 600 — section heads
- `h3` 1.25rem / 600 — card titles, module headers
- `h5` 1.1rem / 600 — sidebar, small-box
- body 1rem / 400 — 1.5 line-height, `.5rem` paragraph margin

### 1.3 Spacing & Layout

- **Grid:** Bootstrap 4 container (1140px max) + 24px gutters default.
- **Gutter scale:** powers of 2 (`0.125rem / .25 / .5 / 1 / 1.5 / 3`) — no
  arbitrary values. `mb-3` (1rem), `mb-4` (1.5rem), `mb-6` (3rem).
- **Radius:** `--radius .5rem` for cards/inputs; `--radius-full` for pills,
  toggles, circular avatars.
- **Admin content-header:** `.container-fluid > .row.mb-2` with flex gap on
  breadcrumbs (`.breadcrumb-item + .breadcrumb-item`).

## 2. Component Library

### 2.1 KPI Card (Dashboard — `admin/modules/dashboard/home.php`)

```
┌──────────────────┐  bg-info (context)
│  [icon]          │  value 42          ← h2 .font-weight-bold
│  Active Staff    │  label             ← small
└──────────────────┘  link → pageUrl(mod)
```

- **Variant by `color` key:** `info|success|warning|danger|teal|primary`.
- `value` is cast to `(int)` at render — never trust a raw DB count string.
- Each card links to the *deepest relevant list* (no dead ends: AC-RPT-01.2).

### 2.2 Flash Banner (all pages)

`renderFlash()` in `admin/show_page.php` content area.

```html
<div class="alert alert-{type} alert-dismissible fade show" role="alert">
  <button type="button" class="close" data-dismiss="alert">×</button>
  {escaped message}
</div>
```
Types: `success`, `error`, `info`, `warning`. One-shot — `flashMessages()`
pulls + clears `$_SESSION['flash']`.

### 2.3 Breadcrumb (admin)

```html
<ol class="breadcrumb float-sm-right">
  <li class="breadcrumb-item"><a href="<?= pageUrl('dashboard') ?>">Home</a></li>
  <li class="breadcrumb-item active">Staffs</li>
</ol>
```
Always ends in the *page title*, never repeats current.

### 2.4 Scroll-Reveal (public site)

Applied to `.reveal` and `[data-reveal-group]`. Stagger: `i * 90ms`, capped
at 450ms. Respects `prefers-reduced-motion`. Falls back to `is-visible`
(eager render) when `IntersectionObserver` is absent — IE11 safe.

### 2.5 Admin-Gesture (security)

Public site footer loads `js/admin-gesture.js` — a **double-click or
long-press** (500ms) on the page body reveals a hidden `/admin/login.php`
link. Rationale: the admin URL must not appear in the public DOM (security
through obscurity as a *layer*, not a wall — the real protection is
`Auth::check()` + IP allow-list + rate limiter).

## 3. Motion & Interaction

| Element | Behavior | Timing |
|---|---|---|
| Theme toggle | `data-mode` attribute swap on `<html>` | instant, no re-render |
| Scroll reveal | Opacity 0→1, translateY 20→0 | 600ms ease |
| Sidebar hover (desktop) | Submenu slide-down | 250ms |
| Form submit → redirect | PRG pattern | N/A (server-side) |
| Toast (if added) | Auto-dismiss after 4s | 300ms fade |

**Hard limit:** no animation exceeds **600ms total**. Long animations read as
"broken" to users with cognitive fatigue.

## 4. Accessibility

- Every interactive control has an explicit `aria-label` (e.g. the mode
  toggle: `aria-label="Switch between light and dark mode"`).
- `role="alert"`/`aria-live="assertive"` on flash banners — screen readers
  announce errors/success without focus steal.
- `<main>` landmark on public site, `<section class="content">` on admin.
- Color is **never the only signal** — KPI cards pair color with an icon
  (`fas fa-users`, `fa-user-check`, …) and a text label.
- Form fields use `<label for>` (Bootstrap floating + horizontal both supported).

## 5. Responsive Strategy

- **Public site:** Mobile-first. Navbar collapses to hamburger at `lg` (992px).
  Hero is `.col-lg-6`; footer stacks to single column on `<md`.
- **Admin panel:** AdminLTE is desktop-first (minimum 768px). No mobile
  optimization attempted — staff use this at desks. A single
  `meta viewport` tag is present, but no media-query work.

## 6. Public-Site vs Admin — Design Continuity

| Property | Shared | Public | Admin |
|---|---|---|---|
| Theme toggle | ✓ | `theme-switcher.js` | `theme-boot.php` (same localStorage) |
| Font pair | ✓ | Poppins + Open Sans | Same + JetBrains Mono |
| Accent palette | ✓ | 6 options | Same 6 options |
| CSS reset | ✓ | Bootstrap 4.6.2 | AdminLTE 3 (bundles BS4) |
| Scroll behavior | ✓ | Scroll-reveal | None (tables, not storytelling) |

The **only** intentional divergence: public site uses a transparent,
scroll-aware navbar (`.site-navbar` with `.scrolled` class on scroll > 50px);
admin uses a fixed sidebar (AdminLTE compact). This matches the user-mental
model: "the website greets me; the admin tool works for me."

## 7. Brand Elements

- **Logo:** configurable via `tbl_cms_setup.logo` (upload path). Rendered at
  `height=32` in site footer, `height=36` in navbar — never scaled wider in
  markup (CSS `max-width: 100%` handles overflow).
- **Tagline:** `tbl_cms_setup.tagline` (default "Your technology partner").
- **Social:** `tbl_cms_setup.{facebook,instagram,linkedin,twitter}` stored as
  full URLs; rendered with the correct Font-Awesome 5 icon + `rel="noopener
  noreferrer"` on external links.
- **Favicon:** `tbl_cms_setup.favicon` — only rendered if set (no default
  fallback shipped, to avoid committing a binary we can't update).

## 8. Image & Media Treatment

- **Hero photos:** `photo_location` stored as path; always `background-image`
  on a `.hero` div with `object-fit: cover` equivalent via
  `background-size: cover; background-position: center`.
- **Gallery:** `tbl_cms_galleries` + `tbl_cms_gallery_categories` — lightbox
  not yet implemented (v1 shows a simple modal-less grid).
- **Team / staff:** circular avatars via `border-radius: var(--radius-full)`.

## 9. Interaction Patterns (PRG)

Every form POST → `admin/operation/*.php` follows **Post-Redirect-Get**:

```php
// operation handler
$db->transaction(fn() => $db->insert('tbl_x', $clean));
setFlash('success', 'Saved.');
redirect(pageUrl('module', 'page'));  // → show_page.php (reload)
```

This is non-negotiable. No long redirects, no inline rendering after write.
The `redirect()` helper falls back to `<meta refresh>` if headers were
already sent — a defensive guard against "stray warning already output"
breaking PRG.

---

*When extending the design: add to this spec first. A design system that lives
only in Figma drifts from code within months. One written in the repo, next to
the CSS it governs, survives the next hire.*