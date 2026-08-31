# SB-Tech Documentation Index

> All project documentation lives in this folder. Each file is a standalone reference.

---

## Quick Navigation

| # | Document | Purpose | Audience |
|---|----------|---------|----------|
| 1 | [OVERVIEW.md](OVERVIEW.md) | Codebase overview — repo layout, architecture, components, testing, conventions | **Start here** — everyone |
| 2 | [ARCHITECTURE.md](ARCHITECTURE.md) | Architecture decision record — layered model, request lifecycle, design decisions | Engineers, tech leads |
| 3 | [PED.md](PED.md) | Pedigree & evolution — origin story, refactor milestones, authorship, lineage | Engineers, project leads |
| 4 | [RULES.md](RULES.md) | Engineering rules & conventions — mandatory coding standards, security rules, naming | **All contributors** |
| 5 | [DESIGN.md](DESIGN.md) | Design system & UX spec — colors, typography, components, responsive strategy | Designers, front-end devs |
| 6 | [Schema.md](Schema.md) | Database schema reference — 84 tables, domains, relationships, seeding, indexing | DBAs, back-end devs |
| 7 | [PRD.md](PRD.md) | Product Requirements Document — user stories, acceptance criteria, NFRs | PMs, QA, engineers |
| 8 | [SYSTEM_MODULES.md](SYSTEM_MODULES.md) | System modules & features — exhaustive catalog of all 15 modules | Builders, PM, QA |
| 9 | [SB_TECH_SYSTEM_ANALYSIS.md](SB_TECH_SYSTEM_ANALYSIS.md) | Design analysis — reference patterns, feature gaps, anti-patterns to fix | Engineers, architects |
| 10 | [PRODUCT_REVIEW.md](PRODUCT_REVIEW.md) | Product review — strengths, risks, MVP recommendations | PMs, founders, engineers |

---

## Recommended Reading Order

### For new team members (onboarding)
1. `OVERVIEW.md` — understand the codebase at a glance
2. `RULES.md` — learn the mandatory conventions before writing code
3. `ARCHITECTURE.md` — understand the layered design and request flows
4. `Schema.md` — study the data model and domain boundaries

### For product / project planning
1. `PRD.md` — what we're building and why
2. `SYSTEM_MODULES.md` — exhaustive feature catalog
3. `PRODUCT_REVIEW.md` — honest assessment, risks, and MVP recommendations
4. `SB_TECH_SYSTEM_ANALYSIS.md` — reference codebase analysis and patterns

### For understanding the system's history
1. `PED.md` — origin, lineage, major refactors, authorship, technical debt
2. `ARCHITECTURE.md` — decisions and trade-offs
3. `OVERVIEW.md` — current state snapshot

### For front-end / design work
1. `DESIGN.md` — design system, tokens, components, responsive strategy
2. `RULES.md` — naming conventions, output escaping, file structure
3. `OVERVIEW.md` — theme system, CSS architecture

---

## File Relationships

```
OVERVIEW.md ←── current-state snapshot of everything below
    │
    ├── ARCHITECTURE.md ←── design decisions & layered model
    ├── PED.md ←── history, lineage, refactor milestones
    ├── RULES.md ←── mandatory engineering conventions
    ├── DESIGN.md ←── visual language & UX specification
    ├── Schema.md ←── database schema reference
    │
    ├── PRD.md ←── user stories & acceptance criteria
    ├── SYSTEM_MODULES.md ←── feature catalog (builds from PRD)
    ├── SB_TECH_SYSTEM_ANALYSIS.md ←── reference analysis & patterns
    └── PRODUCT_REVIEW.md ←── honest review & MVP recommendations
```

---

*All documents are versioned with the code. When you make a decision that changes the system's character, update the relevant file.*

---

## Note on Tooling Compatibility

The gstack skills (`plan-ceo-review`, `design-consultation`, `review`, etc.) conventionally look for `DESIGN.md` and `ARCHITECTURE.md` in the **repo root**. Since this project organizes docs under `docs/`, those skills will not auto-detect our design system or architecture docs. All skills have graceful fallbacks (proceed with universal design principles when not found).

If you need gstack skills to find these docs, create symlinks from the root:

```bash
ln -s docs/DESIGN.md DESIGN.md
ln -s docs/ARCHITECTURE.md ARCHITECTURE.md
```
