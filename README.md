<div align="center">

# 📅 UserManagement Zabbix Module

**Effectively manage inactive users in Zabbix**

![Version](https://img.shields.io/badge/version-2.2.0-blue.svg?style=for-the-badge&logo=git)
![Zabbix](https://img.shields.io/badge/zabbix-7.0%2B-red.svg?style=for-the-badge&logo=zabbix)
![PHP](https://img.shields.io/badge/php-8.0%2B-purple.svg?style=for-the-badge&logo=php)
![License](https://img.shields.io/badge/license-GPL--3.0-green.svg?style=for-the-badge&logo=opensourceinitiative)
![Status](https://img.shields.io/badge/status-production-brightgreen.svg?style=for-the-badge)

[✨ Features](#-features) • [🔄 Workflow](#-the-disablement-workflow) • [🚀 Installation](#-installation) • [📖 Usage](#-usage) • [🔧 Development](#-development) • [📄 License](#-license)

---

</div>

## 📋 Overview

Zabbix has no built-in policy for dormant accounts — a Super Admin either leaves stale logins around indefinitely or manually disables them one by one with no audit trail and no second pair of eyes. **UserMgmt** fixes that: it reviews every user against a configurable inactivity policy, recommends disablement, and enforces a three-step **flag → approve → disable** workflow where each step must be performed by a *different* Super Admin — with every action logged to a searchable, exportable Audit Log.

---

## ✨ Features

### 🚦 Inactivity Policy Engine
- Configurable **minimum account age** and **inactivity threshold** (days)
- Recommends **Disable** or **No Action** per user, computed live against last-login and account-creation data
- Already-disabled accounts are recognized and excluded automatically

### 🔐 Three-Step Approval Workflow
Disabling a user is never a single click:

| Step | Who can do it |
|---|---|
| **Flag for Approval** | Any Super Admin |
| **Approve** | Any Super Admin *except* whoever flagged it |
| **Reject** | Same as Approve — only while still pending |
| **Disable** | Any Super Admin *except* whoever approved it |

- Optional **approver allowlist** (comma-separated usernames) — gates Approve/Reject only; Disable is intentionally open to any Super Admin
- **Comment required** at every step (flag / approve / reject / policy changes) — enforced client-side *and* server-side

### 🎯 Precise Last-Login Detection
- Reads the same `sessions` table Zabbix's own native Users list reads from, via direct `DBselect()` — not just the audit log — so figures match what you'd see in **Administration → Users**
- Falls back to audit-log login correlation only when a session record has aged out of Housekeeping

### 📜 Full Audit Log
- Every flag / approve / reject / disable / settings change recorded with actor, target, full names, timestamp, and comment
- Live filter bar: free-text search, action type, date range
- **Export CSV** — respects whatever's currently filtered/visible

### 📊 Live Summary Cards
Total Users · Never Logged In · Inactive Over Threshold · Recommended Disable · Pending Approvals · Ready to Disable — click any card to jump straight to the relevant view

### 📁 CSV Export (User Review)
- `User ID, Username, Full Name, Account Created, Last Login, Account Age, Inactive For, Activity, Recommendation, Comment`
- Respects active filters and selection

### 🛡️ Non-Destructive Disable
- Never calls `user.delete`
- Adds the user to an auto-created **"Disabled by User Mgmt policy"** group with `users_status = GROUP_STATUS_DISABLED`
- All existing group memberships/permissions are **preserved**, not replaced — fully reversible

---

## 🔄 The Disablement Workflow

```
 Any Super Admin           A DIFFERENT Super Admin         A THIRD Super Admin
     flags a user      →       approves the request     →     disables the user
   (status: pending)            (status: approved)            (status: disabled)
                              ↘
                                rejects the request
                              (status: rejected — dead end)
```

Every transition writes one entry to the Audit Log, comment included.

---

## 🎯 Recommendation Logic

| Recommendation | Condition |
|---|---|
| Already Disabled | User already belongs to the disabled group |
| **Disable** | (account age > threshold **or** unknown) **and** (never logged in **or** last-login age > inactivity threshold) |
| No Action — new account | Account age known and within the minimum age window |
| No Action — active | Everything else |

---

## 🔧 Development

### Project Structure

```
UserMgmt/
├── manifest.json                  # Module id/namespace, action routing
├── Module.php                     # Menu registration (Users → User Mgmt)
├── actions/
│   ├── UserPolicy.php             # Main view controller (user.policy)
│   ├── UserPolicyExecute.php      # AJAX: flag / approve / reject / disable
│   └── UserPolicyConfig.php       # AJAX: save thresholds + approver allowlist
├── views/
│   └── user.policy.php            # Full UI — cards, table, modals, CSS, JS
└── data/
    ├── policy_config.json         # Thresholds + approvers (created on first save)
    ├── approval_queue.json        # Flag/approve/reject/disable request queue
    └── activity_log.json          # Append-only audit trail
```

### Key Architecture Decisions

- **Direct `sessions` table access for last-login.** Since the module runs inside the same PHP process as Zabbix core (not as an external API client), it uses the same `DBselect()` / `DBfetch()` / `dbConditionInt()` globals Zabbix's own Users list uses — this data isn't exposed through any documented API method.
- **No CTag/CHtmlPage object-tree builder.** The view is plain PHP-with-embedded-HTML — the object-tree pattern proved unreliable in testing (silent blank-renders with no logged error).
- **No shared approval-queue class.** Queue read/write is duplicated as private static methods directly inside `UserPolicy.php` and `UserPolicyExecute.php`, avoiding a cross-file class dependency that previously caused autoloading-related fatal errors.
- **Client-side filtering throughout.** Table/audit-log search, action, and date filters all run against the already-rendered DOM — no server re-query per keystroke.
- **Modal stacking handled in JS, not CSS z-index.** Opening an Approve/Reject/Disable modal from inside the Approval Requests popup closes the popup first and reopens it on cancel, rather than relying on layered z-index.

---

## 🚀 Installation

### Prerequisites
- Zabbix 7.0+
- PHP 8.0+
- Super Admin access

### Step 1: Place the Module

Copy the `UserMgmt` folder into your Zabbix frontend's modules directory:

**Zabbix 7.0:**
```bash
sudo cp -r UserMgmt /usr/share/zabbix/modules/
```

**Zabbix 7.4+:**
```bash
sudo cp -r UserMgmt /usr/share/zabbix/ui/modules/
```

### Step 2: Enable the Module

1. Log into the Zabbix web interface
2. Navigate to **Administration → General → Modules**
3. Click **Scan directory** to detect the new module
4. Find **"User Management in Zabbix"** in the list
5. Click **Enable**
6. Refresh your browser

### Step 3: Access the Module

After enabling, find **User Mgmt** under the **Users** menu, next to Authentication.

### Step 4: Configure the Policy

Open **Settings** and set your minimum account age, inactivity threshold, and (optionally) an approver allowlist before flagging your first user.

---

## 📖 Usage

1. **Review** — the User Review table shows every account with its computed recommendation
2. **Flag** — select one or more users recommended for disable and click **Request for Approval**, with a comment
3. **Approve or Reject** — a different Super Admin reviews pending requests in the Approval Requests popup
4. **Disable** — once approved, a third Super Admin completes the disable from the "Ready to Disable" section
5. **Audit** — every step is visible (and exportable) in the Audit Log popup

---

## 🚀 Possible Future Improvements

- Scheduled/automated policy re-evaluation with notifications
- Bulk approve/reject
- Role-based visibility into the Audit Log for non-Super-Admins
- REST/webhook notification on flag/approve/reject/disable
- Configurable retention/rotation for `activity_log.json`

---

## 📜 License

This project is licensed under the **GNU General Public License v3.0** — the same license as Zabbix.

- [Zabbix License Information](https://www.zabbix.com/license)
- [GNU GPL v3.0](https://www.gnu.org/licenses/gpl-3.0.html)

---

## 🤝 Support

- **Issues**: Report bugs via your internal tracker
- **Enhancements**: Feature requests welcome with a clear use case

---

<div align="center">

**Built for governed, auditable Zabbix user account hygiene**

</div>
