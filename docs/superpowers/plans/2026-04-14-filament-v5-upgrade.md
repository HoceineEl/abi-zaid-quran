# Filament v3 → v5 Upgrade Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade the ABI ZAID Quran memorization app from Filament 3.3.39 to Filament 5.x, including the Livewire v3→v4 bump and Tailwind CSS v3→v4.1 migration required by Filament v4/v5.

**Architecture:** Two-hop upgrade path. Filament v5 is a thin compatibility release layered on top of v4 (its only reason for existing is Livewire v4 support). The real work is the v3→v4 migration. Strategy: (1) run Filament's own automated codemod (`filament/upgrade`) for v3→v4 which rewrites ~80% of breaking changes, (2) hand-fix the residual code, (3) upgrade Tailwind to v4 via the official upgrade tool, (4) upgrade Livewire to v4, (5) run the v4→v5 codemod. Commit after every phase so bisection is cheap if something breaks in production smoke tests.

**Tech Stack:** PHP 8.4.19, Laravel 11.46, Livewire 3.6.4 → 4.x, Filament 3.3.39 → 5.x, Tailwind CSS 3.4 → 4.1+, Pest 2.x.

---

## Current-State Baseline

Verified before plan was written:

| Component | Current | v4 Needs | v5 Needs |
|-----------|---------|----------|----------|
| PHP | 8.4.19 | ≥8.2 ✅ | ≥8.2 ✅ |
| Laravel | 11.46.0 | ≥11.28 ✅ | ≥11.28 ✅ |
| Livewire | 3.6.4 | 3.x ✅ | **≥4.0 ⚠️** |
| Filament | 3.3.39 | → 4.x | → 5.x |
| Tailwind | 3.4.19 | **≥4.1 ⚠️** | **≥4.0 ⚠️** |

**Panel providers:** `AdminPanelProvider`, `TeacherPanelProvider`, `AssociationPanelProvider` (3 panels).

**Filament PHP files in `app/Filament/`:** 127 files across Resources, Pages, Widgets, Actions, Exports, Imports, Clusters, RelationManagers.

**Custom Filament theme:** `resources/css/filament/association/theme.css` with its own `tailwind.config.js` — will require Tailwind v4 rewrite (replace `@config` with `@source`).

**Filament plugins (both already v4/v5 compatible):**
- `stechstudio/filament-impersonate: ^3.16` → upgrade to `^5.0` (supports `^4.0|^5.0`, latest v5.3.0)
- `ysfkaya/filament-phone-input: ^3.2` → upgrade to `^4.0` (supports `^4.0|^5.0`, latest v4.1.3)

**Documentation sources used for this plan:**
- v4 upgrade guide: https://filamentphp.com/docs/4.x/upgrade-guide
- v5 upgrade guide: https://filamentphp.com/docs/5.x/upgrade-guide
- Filament v5 announcement: https://laravel-news.com/filament-5

---

## File / Directory Impact Map

Files that *will* be modified by automated tooling (do not hand-edit before the codemod runs):

- `composer.json` — version bumps, plugin renames
- `composer.lock` — regenerated
- `app/Filament/**/*.php` — namespace rewrites (127 files), method signature changes, `schema()` → `components()`, `actions()` → `recordActions()`, `bulkActions()` → `toolbarActions()`
- `app/Providers/Filament/*PanelProvider.php` — any v4-renamed panel APIs
- Possibly `app/Filament/Resources/**` directory structure — only if opt-in restructure command is run

Files that *must* be hand-rewritten (codemod cannot handle):

- `resources/css/filament/association/theme.css` — `@config` → `@source` entries
- `resources/css/filament/association/tailwind.config.js` — **deleted** (theme config moves into CSS)
- `package.json` — Tailwind v3 → v4 dependency set

Files that *may* need touch-up (check after codemod):

- Custom `Filament\Forms\Components\*` subclasses (none found — check)
- Custom render hooks in panel providers
- Custom middlewares using Filament classes
- Blade views under `resources/views/filament/**` that reference removed classes/classes

---

## Ground Rules for Every Task

1. **Commit after each numbered task.** This keeps `git bisect` useful if a v4 page breaks.
2. **Never `--no-verify`** on commits. If a hook fails, fix the underlying issue.
3. **Clear caches religiously.** Run `php artisan optimize:clear` after every composer/code change — Filament caches resource discovery aggressively.
4. **Browser-smoke-test each panel** (`/admin`, `/teacher`, `/association`) after any composer install. "Tests pass" ≠ "app works" for Filament upgrades; the break surface is in rendered pages.
5. **Do not run `php artisan test`** unless explicitly asked (per project rule in `CLAUDE.md`).
6. **The automated scripts print app-specific commands** after they run — always run the commands the script emits, not the ones shown here verbatim (versions may drift).

---

## Task 1: Safety Net — Backup & Baseline Commit

**Files:**
- Create: `database/database_backup_pre_filament_v5.sqlite` (file copy)
- Modify: none
- Test: none

- [ ] **Step 1: Confirm branch state**

Run: `git branch --show-current`
Expected: `upgrade/filament-v5`

- [ ] **Step 2: Back up SQLite database**

```bash
cp database/database.sqlite database/database_backup_pre_filament_v5.sqlite
```

- [ ] **Step 3: Tag the pre-upgrade commit**

```bash
git tag pre-filament-v5-upgrade
```

This gives a fast rollback anchor: `git reset --hard pre-filament-v5-upgrade`.

- [ ] **Step 4: Record baseline versions**

```bash
php -v | head -1 > docs/superpowers/plans/baseline-versions.txt
echo "---" >> docs/superpowers/plans/baseline-versions.txt
php artisan about --only=environment >> docs/superpowers/plans/baseline-versions.txt
composer show filament/filament laravel/framework livewire/livewire >> docs/superpowers/plans/baseline-versions.txt
```

- [ ] **Step 5: Commit pre-existing working-tree changes**

There is already a modification to `composer.json` on this branch. Inspect it first:

```bash
git diff composer.json
```

If the change is meaningful project work, commit it with a descriptive message. If it's stray drift (e.g., a debugger autoload), discard it with `git restore composer.json`. **Do not** bundle unrelated changes into the upgrade commits.

- [ ] **Step 6: Commit baseline artifacts**

```bash
git add database/database_backup_pre_filament_v5.sqlite docs/superpowers/plans/
git commit -m "chore: baseline snapshot before Filament v5 upgrade"
```

---

## Task 2: Run Filament v3 → v4 Automated Upgrade Script

**Files:**
- Modify: `composer.json`, `composer.lock`, potentially every file under `app/Filament/`
- Test: none (codemod only)

The `filament/upgrade` package ships a Rector-based script that rewrites most breaking changes mechanically. Per the docs: *"the automated upgrade script is the first step in upgrading a Filament application to version 4. While it handles many small changes and most breaking changes automatically, it is not a complete replacement for the manual upgrade guide."* ([source](https://filamentphp.com/docs/4.x/upgrade-guide))

- [ ] **Step 1: Install the v4 upgrade package as a dev dependency**

```bash
composer require filament/upgrade:"^4.0" -W --dev
```

Expected: installs `filament/upgrade` alongside v3 Filament (does NOT upgrade Filament yet).

- [ ] **Step 2: Run the codemod**

```bash
vendor/bin/filament-v4
```

Expected: prints a diff summary of rewritten files and outputs a **unique set of composer commands** for this app (e.g., `composer require filament/filament:"^4.0" -W --no-update`). **Copy those commands exactly — do not invent them.**

- [ ] **Step 3: Execute the exact composer commands the script printed**

Typical shape (verify against actual output):

```bash
composer require filament/filament:"^4.0" -W --no-update
composer require stechstudio/filament-impersonate:"^5.0" --no-update
composer require ysfkaya/filament-phone-input:"^4.0" --no-update
composer update
```

If `composer update` fails with unresolvable plugin constraints, the plugin has no v4 release — remove it temporarily (`composer remove <pkg>`) and note it for Task 9 manual replacement.

- [ ] **Step 4: Clear all caches**

```bash
php artisan optimize:clear
```

- [ ] **Step 5: Spot-check a rewritten resource**

Open `app/Filament/Association/Resources/MemorizerResource.php` and verify the codemod rewrote:
- `use Filament\Tables\Actions\Action;` → `use Filament\Actions\Action;`
- `use Filament\Tables\Actions\ActionGroup;` → `use Filament\Actions\ActionGroup;`
- `->actions([...])` → `->recordActions([...])`
- `->bulkActions([...])` → `->toolbarActions([...])`

Do **not** fix anything manually yet — Task 3 lives for that.

- [ ] **Step 6: Commit the codemod output verbatim**

```bash
git add -A
git commit -m "chore(filament): run v3->v4 automated upgrade script"
```

A single commit makes it trivial to audit what the script touched versus what humans touched in later tasks.

---

## Task 3: Hand-Fix v4 Residue the Codemod Missed

**Files:**
- Modify: any file where the next step fails; typically a handful of custom widgets, pages, and form components

**Context for the engineer:** the v4 codemod understands the common patterns but not custom subclasses or exotic imports. Run these greps; each match is a hand-fix candidate.

- [ ] **Step 1: Boot the app — expect errors, triage them**

```bash
php artisan optimize:clear
php artisan serve --port=8003
```

Then in another terminal:

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8003/association/login
```

Expected: `200` (even if login is unstyled). If `500`, read `storage/logs/laravel.log` tail and fix the fatal errors one at a time. Do not try to debug the UI yet — get to a booting state first.

- [ ] **Step 2: Grep for the most common missed patterns**

Run these, in order. Each match is a hand-fix line:

```bash
# Old Forms\Form signature (should now be Schemas\Schema for form() and infolist())
```

Use the Grep tool with `pattern: "public static function form\\(Form \\$form\\)"` across `app/Filament/**/*.php`.

```bash
# Old Infolist signature
```

Use the Grep tool with `pattern: "public static function infolist\\(Infolist \\$infolist\\)"` across `app/Filament/**/*.php`.

```bash
# Old ->schema() on schemas (should be ->components() in v4)
```

Use the Grep tool with `pattern: "->schema\\(\\["` across `app/Filament/**/*.php`. Note: `->schema()` **stays** on layout components (Sections, Tabs, Wizards) — it only changed on the top-level Schema object. Triage each hit.

```bash
# Header/page actions on Pages
```

Use the Grep tool with `pattern: "use Filament\\\\Pages\\\\Actions"` across `app/Filament/**/*.php`. These should all now be `Filament\Actions\...`.

- [ ] **Step 3: Fix each `form(Form $form): Form` to `form(Schema $schema): Schema`**

For every resource file where the signature still uses `Form`:

```php
// Before (v3):
use Filament\Forms\Form;

public static function form(Form $form): Form
{
    return $form
        ->schema([
            TextInput::make('name')->required(),
        ]);
}

// After (v4):
use Filament\Schemas\Schema;

public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            TextInput::make('name')->required(),
        ]);
}
```

Note the **three** changes per method: (1) import, (2) parameter type + name, (3) `->schema([` → `->components([` at the top level only.

- [ ] **Step 4: Fix each `infolist(Infolist $infolist): Infolist` the same way**

```php
// After (v4):
use Filament\Schemas\Schema;

public static function infolist(Schema $schema): Schema
{
    return $schema
        ->components([
            TextEntry::make('name'),
        ]);
}
```

- [ ] **Step 5: Fix RelationManager signatures**

RelationManagers use the same migration. Check every file under `app/Filament/**/RelationManagers/`:

```php
// Before (v3):
public function form(Form $form): Form

// After (v4):
public function form(Schema $schema): Schema
```

Table signatures (`table(Table $table): Table`) stay the same.

- [ ] **Step 6: Fix `Pages\Actions` imports**

```php
// Before (v3):
use Filament\Pages\Actions\Action;
use Filament\Pages\Actions\CreateAction;

// After (v4):
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
```

Header action return type is unchanged (`protected function getHeaderActions(): array`).

- [ ] **Step 7: Boot again, zero fatal errors**

```bash
php artisan optimize:clear
php artisan serve --port=8003
```

Hit `/association/login`, `/admin/login`, `/teacher/login`. All three must return HTTP 200. **Filament pages will look unstyled** because Tailwind is still v3 — that is expected and fixed in Task 4.

- [ ] **Step 8: Commit the hand-fixes**

```bash
git add -A
git commit -m "fix(filament): hand-fix v4 residue the codemod missed"
```

---

## Task 4: Upgrade Tailwind CSS v3 → v4 for the Custom Theme

**Files:**
- Modify: `package.json`, `resources/css/filament/association/theme.css`
- Delete: `resources/css/filament/association/tailwind.config.js`
- Test: `npm run build` must succeed

Per the v4 upgrade guide: *"Custom themes must be upgraded to Tailwind CSS v4. The CSS files should now use `@source` entries to tell Tailwind where to find classes, replacing the old `@config 'tailwind.config.js'` directive."* ([source](https://filamentphp.com/docs/4.x/upgrade-guide))

- [ ] **Step 1: Run Tailwind's official upgrade tool**

```bash
npx @tailwindcss/upgrade
```

This rewrites `package.json`, installs `@tailwindcss/vite` and `tailwindcss@^4`, and converts `@apply` directives where possible.

- [ ] **Step 2: Rewrite the Filament theme entrypoint**

Open `resources/css/filament/association/theme.css`. Replace the top three lines:

```css
/* Before (v3): */
@import '/vendor/filament/filament/resources/css/theme.css';

@config 'tailwind.config.js';
```

```css
/* After (v4): */
@import '../../../../vendor/filament/filament/resources/css/theme.css';

@source '../../../../app/Filament/Association/**/*';
@source '../../../../resources/views/filament/association/**/*.blade.php';
@source '../../../../resources/views/livewire/association/**/*.blade.php';
@source '../../../../resources/views/badges/association/**/*.blade.php';
@source '../../../../app/**/*.php';
```

The `@source` entries mirror what the old `tailwind.config.js` `content` array declared — copy them 1:1.

- [ ] **Step 3: Delete the now-unused theme tailwind config**

```bash
rm resources/css/filament/association/tailwind.config.js
```

Tailwind v4 has **no** `tailwind.config.js` for theme files — theme config is CSS-native.

- [ ] **Step 4: Audit the root `tailwind.config.js`**

Open `/Users/mac/Herd/abi-zaid-quran/tailwind.config.js`. If it exists and is referenced by `resources/css/app.css` via `@config`, it will also need the same `@config` → `@source` migration. If `resources/css/app.css` has no `@config` and is just plain CSS, you can leave the root config file alone (Tailwind v4 will ignore it) or delete it for cleanliness — your call.

- [ ] **Step 5: Build assets**

```bash
npm run build
```

Expected: build succeeds, outputs to `public/build/`. If it fails with `Cannot resolve @config`, you missed a `@config` directive somewhere — grep and fix.

- [ ] **Step 6: Smoke-test the styled panel**

```bash
php artisan serve --port=8003
# In another terminal or browser: open http://127.0.0.1:8003/association/login
```

The panel must render with full Tailwind styling — sidebar, buttons, forms all present. If styling is partial or absent, the `@source` paths are wrong — add the missing directory.

- [ ] **Step 7: Commit the Tailwind migration**

```bash
git add -A
git commit -m "chore(tailwind): upgrade theme to Tailwind CSS v4"
```

---

## Task 5: Browser Smoke Test of Filament v4

**Files:** none (test only)

Before moving to v5, prove v4 works end-to-end on all three panels. The goal is to catch rendering/route issues the compiler couldn't.

- [ ] **Step 1: Start the full dev stack**

```bash
composer run dev
```

This starts `php artisan serve`, the queue listener, and `npm run dev` concurrently.

- [ ] **Step 2: Log in to `/association` panel and verify**

In a browser:
- Log in successfully
- Dashboard renders (widgets don't crash)
- `Memorizers` list loads with rows
- Open a Memorizer's edit page — form renders, no 500
- Open `Teachers`, `Guardians`, `Payments`, `Groups` resources — all list and edit pages
- Custom page: `Scan Attendance`

For each broken page, tail `storage/logs/laravel.log`, identify the class/method, fix, refresh. Most v4 issues at this stage are exotic custom actions or widgets the codemod didn't understand.

- [ ] **Step 3: Repeat for `/admin` panel**

Same checklist: dashboard, every resource's list and edit page, custom pages (`ScanQrCode`, `SubtitleCleaner`, `ReminderReport`).

- [ ] **Step 4: Repeat for `/teacher` panel**

Same checklist.

- [ ] **Step 5: Exercise Exports and Imports**

From `/association`, trigger a Memorizer export (bulk action). Verify it dispatches and the resulting file downloads. Do the same for Progress and StudentDisconnection exports in `/admin`. Run a Memorizer import with a small test CSV to confirm `Filament\Actions\ImportAction` still works end-to-end.

- [ ] **Step 6: Exercise the WhatsApp actions**

The project has many custom WhatsApp-related Filament actions (`SendWhatsAppBulkToDisconnectedAction`, etc). Trigger at least one from a table row and one bulk action. Don't actually send production messages — if needed, stub the service or use a dev phone number.

- [ ] **Step 7: Commit any fixes found during smoke-testing**

```bash
git add -A
git commit -m "fix(filament): address v4 smoke-test findings"
```

If no fixes were needed, skip this commit.

---

## Task 6: Upgrade Livewire v3 → v4

**Files:**
- Modify: `composer.json`, `composer.lock`, possibly a small number of Livewire components under `app/Livewire/` and `app/Http/Livewire/`
- Test: Filament panels must still render; Livewire routes `/livewire/update` must return 200 on interactions

Filament v5's only hard requirement over v4 is Livewire v4. Do this as a distinct phase so if Livewire v4 breaks something, it's cleanly attributable. Before starting, read the official Livewire v4 upgrade guide at https://livewire.laravel.com/docs/upgrading — many changes are automatic but a few are not.

- [ ] **Step 1: Bump Livewire**

```bash
composer require livewire/livewire:"^4.0" -W
```

- [ ] **Step 2: Clear caches and reboot**

```bash
php artisan optimize:clear
composer run dev
```

- [ ] **Step 3: Hand-triage any `app/Livewire/**` components that broke**

Use the Grep tool with `pattern: "extends Component"` in `app/Livewire/` to find every component, then open each and verify it still compiles against Livewire v4's API (look for deprecated lifecycle hooks, removed `emit` vs `dispatch`, etc). Use the Livewire v4 upgrade guide at https://livewire.laravel.com/docs/upgrading as your reference.

- [ ] **Step 4: Browser re-smoke of each panel's interactive features**

Specifically: any form submission, any modal open/close, any bulk action. If an interaction 500s, check `storage/logs/laravel.log` for Livewire errors.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore(livewire): upgrade to v4"
```

---

## Task 7: Run Filament v4 → v5 Automated Upgrade Script

**Files:**
- Modify: `composer.json`, `composer.lock`, a small number of files (v5 has almost no code-level breaking changes beyond Livewire v4)

Per the v5 upgrade guide: *"Apart from Livewire v4 support, Filament v5 has no additional changes over v4."* ([source](https://filamentphp.com/docs/5.x/upgrade-guide))

- [ ] **Step 1: Install the v5 upgrade script**

```bash
composer require filament/upgrade:"^5.0" -W --dev
```

- [ ] **Step 2: Run the codemod**

```bash
vendor/bin/filament-v5
```

Expected: very small diff, mostly composer command output.

- [ ] **Step 3: Execute the composer commands the script printed**

Typical shape (verify against actual output — do not assume):

```bash
composer require filament/filament:"^5.0" -W --no-update
composer update
```

- [ ] **Step 4: Clear caches**

```bash
php artisan optimize:clear
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore(filament): upgrade v4 -> v5"
```

---

## Task 8: Remove the Upgrade Packages

**Files:** `composer.json`, `composer.lock`

The `filament/upgrade` package is one-shot — it's only needed while running the codemod. Remove it to keep production installs lean.

- [ ] **Step 1: Remove**

```bash
composer remove filament/upgrade --dev
```

- [ ] **Step 2: Verify the app still boots**

```bash
php artisan optimize:clear
composer run dev
```

Smoke-check `/association` — dashboard renders.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: remove filament/upgrade helper package"
```

---

## Task 9: Plugin Version Audit & Replacement

**Files:** `composer.json`, `composer.lock`, possibly a small number of Filament files if a plugin's API changed

Two of the three Filament plugins already confirm v5 compatibility (checked on Packagist 2026-04-14):

| Plugin | v3 constraint | v5-compatible | Action |
|--------|---------------|---------------|--------|
| `stechstudio/filament-impersonate` | `^3.16` | v5.3.0+ (`^4.0\|^5.0`) | Auto-upgraded in Task 2 |
| `ysfkaya/filament-phone-input` | `^3.2` | v4.1.3+ (`^4.0\|^5.0`) | Auto-upgraded in Task 2 |

- [ ] **Step 1: Confirm final installed versions**

```bash
composer show stechstudio/filament-impersonate ysfkaya/filament-phone-input | head -20
```

Both should list a v4/v5-compatible version. If Task 2 dropped either one because of constraints, reinstall now:

```bash
composer require stechstudio/filament-impersonate:"^5.0"
composer require ysfkaya/filament-phone-input:"^4.0"
```

- [ ] **Step 2: Exercise the plugin-specific UI**

- **filament-impersonate**: in `/admin` as the super-admin, click the Impersonate action on a User row — verify it logs you in as that user and you can revert.
- **filament-phone-input**: open a Memorizer edit page — the phone field must render with country flag picker, auto-format on input, and validate on save.

- [ ] **Step 3: Commit if anything changed**

```bash
git add -A
git commit -m "chore(plugins): confirm Filament v5 plugin compatibility"
```

If neither plugin needed changes, skip this commit.

---

## Task 10: Full Regression Smoke Test

**Files:** none — verification only.

The upgrade is *done* when every user-facing path in the app works. Do a final round against a checklist, using the production-representative SQLite database (still intact from Task 1 baseline).

- [ ] **Step 1: Boot the full dev stack**

```bash
composer run dev
```

- [ ] **Step 2: `/association` panel — full pass**

- Login
- Dashboard: every widget renders (8 widgets: AssociationStatsOverview, DetailedStatsOverview, AttendanceChart, GroupsStatsChart, PaymentsChart, MemorizationProgressStats, AttendanceTrendsWidget, ComprehensivePerformanceWidget)
- Memorizers: list → filter → edit → create → delete
- Teachers: list → edit → Attendance Logs relation → Reminder Logs relation → WhatsApp Messages relation
- Guardians: list → edit → Memorizers relation
- Groups: list → edit → view → Students relation → Payments relation
- Payments: list → create → edit
- Custom page: `Scan Attendance` (camera scanner flow)

- [ ] **Step 3: `/admin` panel — full pass**

- Login
- Dashboard + widgets (GroupProgressChart, QuranProgramStatsOverview, StudentProgressTimeline, UserActivityStats, ReminderStatsOverview, ReminderGroupsTable)
- Every resource's list + edit: User, Student, Group, Progress, Page, Message, StudentDisconnection, WhatsAppSession
- Custom pages: ScanQrCode, SubtitleCleaner, ReminderReport, Dashboard

- [ ] **Step 4: `/teacher` panel — full pass**

- Login as a teacher user
- Every resource accessible to a teacher's role

- [ ] **Step 5: Exports**

- Trigger at least one Export bulk action per exporter: MemorizerExporter, ProgressExporter, StudentDisconnectionExporter
- Confirm the file downloads and opens in Excel/Numbers without corruption

- [ ] **Step 6: Imports**

- Run a small test Memorizer import
- Confirm inserted records appear in the list

- [ ] **Step 7: WhatsApp flow (non-production data)**

- Trigger `SendWhatsAppMessageToDisconnectedAction` for a single test row
- Confirm job is dispatched (check `queue` worker logs)
- Verify `WhatsAppMessageHistory` row is created

- [ ] **Step 8: If anything broke, fix and commit per-fix**

Each fix gets its own commit — do not bundle unrelated fixes.

- [ ] **Step 9: Final summary commit marker**

```bash
git tag filament-v5-upgrade-complete
```

---

## Task 11: Remove Backup Artifacts & Finalize

**Files:** `database/database_backup_pre_filament_v5.sqlite`, `database/database_backup_20260411_181249.sqlite`

- [ ] **Step 1: Confirm branch is green**

```bash
git status
```

Expected: clean working tree.

- [ ] **Step 2: Remove the staged backup**

Only after Task 10 passed end-to-end. The git tag `pre-filament-v5-upgrade` is the real rollback anchor.

```bash
rm database/database_backup_pre_filament_v5.sqlite
rm database/database_backup_20260411_181249.sqlite
git add -A
git commit -m "chore: remove pre-upgrade database backups"
```

- [ ] **Step 3: Push branch (only when the user asks)**

Per `CLAUDE.md`: *"Don't commit until explicitly asked"* and don't push/open PRs without explicit request. Stop here and surface to the user.

---

## Rollback Procedure

If catastrophic breakage is discovered after merge:

```bash
git checkout master
git reset --hard pre-filament-v5-upgrade   # if not yet pushed
# or
git revert <merge-commit-sha>              # if pushed
cp database/database_backup_pre_filament_v5.sqlite database/database.sqlite
composer install
npm install
npm run build
```

## Known Risk Hotspots (pay extra attention)

1. **`MemorizerResource` and `GroupResource`** — the largest resources with the most custom actions; most likely place for codemod blind spots.
2. **Custom widgets extending chart widgets** — v4 renamed some chart widget base classes; check `AttendanceChart`, `PaymentsChart`, `GroupsStatsChart`.
3. **`renderHook` calls in panel providers** — hook name enums moved; `PanelsRenderHook::BODY_START` may be renamed.
4. **Custom `Filament\Forms\Components\*` subclasses** — if any exist, their parent class API may have changed; they won't be in the codemod's allow-list.
5. **The `SubtitleCleaner` and `ScanQrCode` custom pages** — these bypass normal Resource pattern and may have panel-specific code that v4 tightened.
6. **`stechstudio/filament-impersonate`** — v5.x is a rewrite from v3.x; the impersonation banner HTML may need re-styling against the new theme.
