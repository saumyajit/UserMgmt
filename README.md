<div align="center">

# 📅 UserManagement Zabbix Module

**Effectively manage users in Zabbix**

![Version](https://img.shields.io/badge/version-2.2.0-blue.svg?style=for-the-badge&logo=git)
![Zabbix](https://img.shields.io/badge/zabbix-7.0%2B-red.svg?style=for-the-badge&logo=zabbix)
![PHP](https://img.shields.io/badge/php-8.0%2B-purple.svg?style=for-the-badge&logo=php)
![License](https://img.shields.io/badge/license-GPL--3.0-green.svg?style=for-the-badge&logo=opensourceinitiative)
![Status](https://img.shields.io/badge/status-production-brightgreen.svg?style=for-the-badge)

[✨ Features](#-features) • [🚀 Installation](#-installation) • [📖 Usage](#-usage) • [🔧 Development](#-development) • [📄 License](#-license)

---

</div>

## 📋 Overview

# User Management in Zabbix (UserMgmt)

A Zabbix 7.x frontend module that surfaces inactive/dormant user accounts and provides a governed, auditable workflow for disabling them — because Zabbix has no built-in policy for this.

- **Module ID / namespace:** `UserMgmt`
- **Menu location:** Users → User Mgmt (added after "Authentication")
- **Access:** Super Admin only

---

## 1. What it does

- Reviews every Zabbix user against a configurable inactivity policy (minimum account age + inactivity threshold) and recommends **Disable** or **No Action**.
- Requires disablement to go through a **three-step approval workflow** — flag → approve → disable — enforced by separation-of-duties rules, not just a single click.
- Logs every action (flag, approve, reject, disable, settings changes) to a persistent, filterable, exportable **Audit Log**.
- Never uses Zabbix's `user.delete` — disabling means adding the user to a dedicated, auto-created user group with `users_status = GROUP_STATUS_DISABLED`, which **preserves** all of the user's existing group memberships/permissions rather than replacing them. This is fully reversible by removing them from that one group.

---

## 2. File structure

```
LICENSE
README.md
manifest.json                  — module id/namespace, action routing
Module.php                     — registers the "User Mgmt" menu item
actions/
    UserPolicy.php              — main view controller (user.policy)
    UserPolicyExecute.php       — AJAX endpoint: flag/approve/reject/disable (user.policy.execute)
    UserPolicyConfig.php        — AJAX endpoint: save policy thresholds/approvers (user.policy.config)
views/
    user.policy.php              — the entire UI: cards, table, modals, CSS, JS (single file)
data/
    policy_config.json          — thresholds + approver allowlist (created on first save)
    approval_queue.json         — flag/approve/reject/disable request queue
    activity_log.json           — append-only audit trail (all actions)
```

All three action classes live under `namespace Modules\UserMgmt\Actions` and extend Zabbix's `CController`. Both `UserPolicyExecute` and `UserPolicyConfig` are pure AJAX endpoints — no view — that `header('Content-Type: application/json')`, echo, and `exit`.

---

## 3. The disablement workflow

Disabling a user is **never** a single click. It requires three separate actions, and — critically — **each step must be performed by a different Super Admin** than the one before it:

```
 Any Super Admin           A DIFFERENT Super Admin         A THIRD Super Admin
     flags a user      →       approves the request     →     disables the user
   (status: pending)            (status: approved)            (status: disabled)
```

| Step | Who can do it | Enforcement |
|---|---|---|
| **Flag for Approval** | Any Super Admin, for any user the policy recommends disabling | — |
| **Approve** | Any Super Admin *except* whoever flagged it | `enforceApproverPermission()` (approver allowlist, if configured) + `flagged_by !== actor` check |
| **Reject** | Same as Approve | Same as Approve. Only valid while status is still `pending` — an already-approved request can no longer be rejected, only disabled or left as-is. |
| **Disable** | Any Super Admin *except* whoever approved it | `approved_by !== actor` check. **Not** gated by the approver allowlist — any Super Admin may perform the final disable. |

A **comment is mandatory** at every step (flag, approve, reject) and for policy/settings changes — enforced both client-side (JS, with a focus-and-alert) and server-side (`UserPolicyExecute`/`UserPolicyConfig` reject empty comments outright, so the UI check can't be bypassed by a direct API call).

### Approver allowlist
Configurable in Settings as a comma-separated list of usernames. If **empty**, any Super Admin may approve/reject. If **set**, only listed usernames may approve or reject (the Disable step is *not* restricted by this list — see table above).

---

## 4. Inactivity policy

Configurable via the Settings modal, saved to `data/policy_config.json`:

| Setting | Default | Meaning |
|---|---|---|
| `min_account_age_days` | 60 | Minimum account age before it's eligible for the policy |
| `inactivity_threshold_days` | 45 | Days without login before an eligible account is flagged inactive |
| `approvers` | *(empty = anyone)* | Allowlist for the Approve/Reject step |

**Recommendation logic** (`UserPolicy::doAction()`):

```
IF already in "Disabled by User Mgmt policy" group     → No Action (Already Disabled)
ELSE IF (account_age > min_account_age_days OR account_age unknown)
     AND (never logged in OR last_login_age > inactivity_threshold_days)
                                                         → Disable (reason: never_logged_in | inactive)
ELSE IF account_age known AND account_age <= min_account_age_days
                                                         → No Action (new_account)
ELSE                                                     → No Action (active)
```

Every settings change is itself logged to the Audit Log with a machine-generated diff (e.g. `Min account age: 60 → 45 days | Approvers: [none] → [alice, bob]`), prefixed with the required free-text reason you provide.

---

## 5. Data sources — and a deliberate departure from the audit log

Zabbix's API doesn't expose "account creation time" or "last login time" directly, so this module derives them:

- **Account creation time:** `API::AuditLog()->get(['filter' => ['action' => 0, 'resourcetype' => 0]])` (Add / User), keyed by `resourceid` (the new user).
- **Last login time:** two layered sources, in priority order:
  1. **Primary — direct `sessions` table query.** Since this module runs *inside* the Zabbix frontend process (not as an external API client), it has access to the same `DBselect()` / `DBfetch()` / `dbConditionInt()` globals Zabbix's own core code uses. It replicates exactly what the native Administration → Users list does for its "Login" column: `SELECT userid, MAX(lastaccess) FROM sessions GROUP BY userid`. This is deliberately **not** available through any documented API method — it requires this level of access, which a normal module has.
  2. **Fallback — audit log correlation.** `action = 8` (Login) audit records, for any user whose `sessions` row has aged out under Housekeeping's session-lifetime setting (default 365 days). Without this fallback, a genuinely-once-active user would incorrectly show "Never" once their session record is purged.

     > **Why not just use the audit log alone?** An audit-log Login event only captures the *moment of authentication*. `sessions.lastaccess` is continuously updated on **every authenticated request** while a session stays alive (`CWebUser::checkAuthentication(..., extend: true)`), so it reflects ongoing activity, not a single login moment — this is why the native Users page and a naive audit-log-only approach can diverge by hours or days for the same user.

---

## 6. Audit Log

A dedicated popup (header button, or click either the "Pending Approvals" or "Ready to Disable" summary card) showing every logged action:

**Columns:** Time · Actor · Action · Target User · Full Name · Comment

- **Full Name** shows the *actor's* name (who performed the action), computed automatically inside `logActivity()` from `\CWebUser::$data` — not the target user's name. This applies uniformly across all action types, including Settings Updates (which have no target user at all).
- **Filters:** free-text search (matches actor/target/comment, including the full untruncated comment behind a "Details" button), Action dropdown, and a from/to date range — all client-side, instant.
- **Export CSV:** respects whatever the filters currently have visible. Columns: `Time, Actor, Action, Target User, Full Name, User ID, Comment`.

Logged action types: `flag`, `approve`, `reject`, `disable`, `settings_update`.

---



## 7. Main table (User Review)

**Columns:** checkbox · User (username + User ID) · Full Name · Account Created · Last Login · Account Age · Inactive For · Activity · Recommendation · Action

- Client-side filter bar: username search, Activity dropdown, Recommendation dropdown.
- Bulk selection + "Request for Approval" for eligible users; per-row action button adapts to state (Request Approval / Disable / — for not-yet-eligible or already-resolved users).
- **Export CSV** (respects current filters/selection visibility). Column order: `User ID, Username, Full Name, Account Created, Last Login, Account Age, Inactive For, Activity, Recommendation, Comment`.

**Summary cards:** Total Users · Never Logged In · Inactive Over Threshold · Recommended Disable · Pending Approvals · Ready to Disable.

---

## 8. Frontend architecture notes

- **No CTag/CHtmlPage object-tree builder.** An earlier attempt at Zabbix's native object-tree view pattern proved unreliable in this environment (a chaining bug, then a silent blank-render with no logged error). The view is plain PHP-with-embedded-HTML instead — proven stable.
- **All CSS/JS lives inline in `views/user.policy.php`** inside a single `<style>`/`<script>` block, output via `CTag(..., false, $html)` (encode disabled — this bit a prior version badly: `encode=true` HTML-escaped the CSS/JS and silently broke every button/filter/modal).
- **No cross-file class dependency for the approval queue.** Queue read/write logic is duplicated as private static methods directly inside `UserPolicy.php` and `UserPolicyExecute.php`, rather than a shared `Lib\CApprovalQueue` class — an earlier version of that class caused repeated fatal-error regressions around module autoloading.
- **All filtering/sorting/search is client-side JS** against the already-rendered table — no server re-query on filter change.
- **Modal stacking:** all `.umg-modal-backdrop` elements share one `z-index`. Opening an Approve/Reject/Disable modal *from inside* the Approval Requests popup closes the popup first (`openFromPending()`) and reopens it on cancel (`closeAndMaybeReopenPending()`), rather than relying on z-index layering, which proved unreliable with multiple simultaneously-open backdrops.

---

## 9. Known limitations / things to be aware of

- **Approval queue and audit log are flat JSON files** (`data/approval_queue.json`, `data/activity_log.json`), not database tables. Fine at the scale this is designed for; not built for high-volume concurrent writes.
- **`sessions` table access is a deliberate, informed departure from "API-only."** It mirrors exactly what Zabbix's own native frontend code does in the same execution context, but if Zabbix ever changes its internal `sessions` schema in a future major version, this one query is the thing to revisit.
- **PHP opcache staleness has bitten deployments before.** If a fix doesn't seem to take effect after re-uploading a file, confirm the file on disk actually changed and consider whether `opcache.validate_timestamps=0` requires a reload.
- **Old activity log / approval queue entries predate certain fields** (e.g. `name`/`surname`, `actor_name`/`actor_surname`). These render gracefully as blank — nothing retroactively backfills historical entries.

---

## 10. Troubleshooting quick reference

| Symptom | Likely cause |
|---|---|
| A recent code fix "isn't working" | Live server likely still serving a stale file — verify the file on disk, consider opcache |
| "You are not on the approver list for this module" when trying to **disable** | Should not happen — Disable is intentionally *not* gated by the approver allowlist, only Approve/Reject are. If seen, the allowlist enforcement was mistakenly applied to the disable branch. |
| Last Login shows an older time than the native Zabbix Users page | Expected only if this module is on an older version without the `sessions`-table fix (§5) — the native page reflects ongoing session activity, this module's fallback path reflects a discrete login event |
| A modal opens behind another already-open modal | Should not happen post-fix — nested action modals close their parent popup first |# UserMgmt
