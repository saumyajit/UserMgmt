<?php
/** @var CView $this */
/** @var array $data */

$config = $data['config'];
$summary = $data['summary'];
$pending_queue = $data['pending_queue'];
$activity_log = $data['activity_log'];
$superadmins = $data['superadmins'];

function umg_esc($v) {
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$action_labels = [
	'flag' => _('Flagged for approval'),
	'disable' => _('Disabled'),
	'approve' => _('Approved & disabled'),
	'reject' => _('Rejected')
];
$action_classes = [
	'flag' => 'umg-badge-warning',
	'disable' => 'umg-badge-danger',
	'approve' => 'umg-badge-danger',
	'reject' => 'umg-badge-info'
];

// Approvers configured today (usernames), used to pre-seed the chip picker below.
$configured_approvers = is_array($config['approvers']) ? $config['approvers'] : [];

// Threshold above which the Audit Log's Comment column truncates and shows a
// "Details" button that opens the full text in a secondary popup.
define('UMG_LOG_COMMENT_TRUNCATE', 60);
$pending_count = count($pending_queue);
?>

<!-- NEW structure, matching the Maintenance Calendar reference exactly:
     the title block is a separate ABSOLUTELY POSITIONED element centered
     against the full header width (left:50%; transform:translateX(-50%)),
     not a flex sibling of the buttons. That's what makes it truly centered
     regardless of how many buttons are on the right, instead of only being
     centered within the leftover space next to them. -->
<div class="umg-page-header">
	<div class="umg-hdr-title-center">
		<div class="umg-hdr-title-main">
			<span class="umg-page-header-icon">👥</span> <?= umg_esc($data['title']) ?>
		</div>
		<div class="umg-hdr-title-sub">
			<?= _('Surfaces inactive Zabbix accounts for review and secure disabling through an auditable approval workflow.') ?>
		</div>
	</div>
	<div class="umg-hdr-right">
		<button type="button" class="umg-btn umg-btn-header" id="umg-audit-log-btn">
			<span class="umg-btn-icon">&#128337;</span><?= _('Audit Log') ?>
		</button>
		<button type="button" class="umg-btn umg-btn-header" id="umg-pending-btn">
			<span class="umg-btn-icon">&#9203;</span> <?= _('Approval Requests') ?>
		</button>
		<button type="button" class="umg-btn umg-btn-header" id="umg-export-csv">
			📤 <?= _('Export CSV') ?>
		</button>
		<button type="button" class="umg-btn umg-btn-header" id="umg-settings-btn">
			⚙️ <?= _('Settings') ?>
		</button>
	</div>
</div>

<style>
:root {
    --umg-indigo: #4f5fed;
    --umg-indigo-dark: #3c48c9;
    --umg-blue: #2f7dd1;
    --umg-teal: #17a2b8;
    --umg-green: #22a06b;
    --umg-amber: #e0a721;
    --umg-red: #e05260;
    --umg-red-dark: #d13a49;
    --umg-purple: #8a5fd6;
    --umg-ink: #1f2937;
    --umg-modal-header-bg: linear-gradient(135deg, #1a3a5c 0%, #1e5799 55%, #2e86c1 100%);
}

/* Page header — same technique as #calendarHeader / .hdr-title-center in the
   Maintenance Calendar reference: relative positioning context + min-height
   on the header, with the title block absolutely centered against the FULL
   width via left:50% + translateX(-50%), and the buttons pinned right with
   margin-left:auto. This guarantees true centering no matter how many
   buttons are on the right, unlike flex space-between. */
.umg-page-header {
    position: relative;
    display: flex;
    align-items: center;
    min-height: 80px;
    background: linear-gradient(135deg, #1a3a5c 0%, #1e5799 55%, #2e86c1 100%);
    border-radius: 20px;
    padding: 0 20px;
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.22);
    flex-wrap: wrap;
    row-gap: 8px;
}

.umg-hdr-title-center {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    line-height: 1.25;
    max-width: 62%;
    color: #fff;
}

.umg-hdr-title-main {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
    text-shadow: 0 1px 2px rgba(0,0,0,0.15);
    padding-bottom: 4px;
}

.umg-page-header-icon {
    font-size: 28px;
    vertical-align: 2px;
}

.umg-hdr-title-sub {
    font-size: 16px;
    opacity: .85;
    font-weight: 500;
    letter-spacing: .2px;
    padding-top: 10px;
}

.umg-hdr-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

/* Header buttons: translucent glass pills, matching .hdr-btn in the
   reference. Selector kept at element+2classes specificity so it can never
   be silently overridden by the plain "button.umg-btn" rule below. */
button.umg-btn.umg-btn-header {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0px 10px;
    background: rgba(255,255,255,0.15);
    color: #fff;
    border: 1.5px solid rgba(255,255,255,0.55);
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    height: auto;
    white-space: nowrap;
    transition: all .18s ease;
}

button.umg-btn.umg-btn-header:hover {
    background: rgba(255,255,255,0.30);
    border-color: #fff;
}

button.umg-btn.umg-btn-header:active {
    transform: translateY(1px);
}

body, .wrapper {
    background: linear-gradient(180deg, #f3f5fb 0%, #eef1fa 100%);
    font-family: "Trebuchet MS", "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Tahoma, sans-serif;
}

.wrapper {
    display: flex;
    flex: 1;
    flex-direction: column;
    overflow: auto;
    position: relative;
    padding: 4px;
}

/* Summary cards */
.umg-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 16px;
    margin: -6px 0 16px 0;
}

.umg-card {
    position: relative;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e7e9f2;
    border-radius: 24px;
    padding: 18px 18px 16px 18px;
    box-shadow: 0 2px 8px rgba(31,41,55,0.06);
    transition: box-shadow .18s ease, transform .18s ease;
}

.umg-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--umg-blue), var(--umg-teal));
}

.umg-card::after {
    content: '';
    position: absolute;
    top: -30px;
    right: -30px;
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(47,125,209,0.10), transparent 70%);
}

.umg-card:hover {
    box-shadow: 0 10px 26px rgba(31,41,55,0.14);
    transform: translateY(-2px);
}

.umg-card.umg-accent-danger::before {
    background: linear-gradient(90deg, var(--umg-red), #ff8a80);
}

.umg-card.umg-accent-danger::after {
    background: radial-gradient(circle, rgba(224,82,96,0.12), transparent 70%);
}

.umg-card.umg-accent-warning::before {
    background: linear-gradient(90deg, var(--umg-amber), #ffd166);
}

.umg-card.umg-accent-warning::after {
    background: radial-gradient(circle, rgba(224,167,33,0.14), transparent 70%);
}

.umg-card.umg-accent-ok::before {
    background: linear-gradient(90deg, var(--umg-green), #7be0a8);
}

.umg-card.umg-accent-purple::before {
    background: linear-gradient(90deg, var(--umg-purple), #c3a6ef);
}

.umg-card.umg-accent-purple::after {
    background: radial-gradient(circle, rgba(138,95,214,0.14), transparent 70%);
}

.umg-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.umg-card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    border-radius: 8px;
    font-size: 14px;
    background: linear-gradient(135deg, rgba(47,125,209,0.15), rgba(23,162,184,0.15));
    color: var(--umg-blue);
}

.umg-card.umg-accent-danger .umg-card-icon {
    background: linear-gradient(135deg, rgba(224,82,96,0.16), rgba(255,138,128,0.16));
    color: var(--umg-red-dark);
}

.umg-card.umg-accent-warning .umg-card-icon {
    background: linear-gradient(135deg, rgba(224,167,33,0.18), rgba(255,209,102,0.18));
    color: #8a6200;
}

.umg-card.umg-accent-ok .umg-card-icon {
    background: linear-gradient(135deg, rgba(34,160,107,0.16), rgba(123,224,168,0.16));
    color: var(--umg-green);
}

.umg-card.umg-accent-purple .umg-card-icon {
    background: linear-gradient(135deg, rgba(138,95,214,0.18), rgba(195,166,239,0.18));
    color: var(--umg-purple);
}

.umg-card-title {
    color: #6b7280;
    font-size: 18px;
    text-transform: uppercase;
    letter-spacing: .04em;
    font-weight: 700;
}

.umg-card-value {
    font-size: 30px;
    font-weight: 800;
    color: var(--umg-ink);
    line-height: 1;
    text-align: center;
}

/* Generic panel */
.umg-panel {
    background: #fff;
    border: 1px solid #e7e9f2;
    border-radius: 16px;
    padding: 18px 20px 20px 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(31,41,55,0.05);
}

.umg-panel h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 14px 0;
    color: var(--umg-ink);
    display: flex;
    align-items: center;
    gap: 8px;
}

.umg-panel h2 .umg-h2-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 7px;
    font-size: 13px;
    background: linear-gradient(135deg, rgba(79,95,237,0.14), rgba(47,125,209,0.14));
    color: var(--umg-indigo);
}

/* Pending Approvals list (rendered inside the Approval Requests modal) */
.umg-approval-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 7px 4px;
    border-bottom: 1px dashed #ecd9a8;
    flex-wrap: wrap;
}

.umg-approval-item:last-child {
    border-bottom: none;
}

.umg-approval-meta {
    flex: 1;
    min-width: 220px;
    display: flex;
    align-items: baseline;
    gap: 6px;
    flex-wrap: wrap;
    font-size: 12.5px;
    line-height: 1.5;
}

.umg-approval-meta .umg-username {
    font-size: 13px;
}

.umg-approval-meta .umg-subtext-inline {
    font-size: 11px;
    color: #8a94a3;
}

.umg-approval-dot {
    color: #d8c48a;
}

.umg-approval-flagged-inline {
    font-size: 11.5px;
    color: #8a94a3;
}

.umg-approval-comment-inline {
    font-size: 11.5px;
    color: #7a6420;
    background: #fdf6e3;
    border: 1px solid #f2e2ae;
    border-radius: 10px;
    padding: 1px 9px;
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.umg-approval-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-shrink: 0;
}

/* Filters */
.umg-filter-row {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.umg-filter {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.umg-filter label {
    font-size: 14px;
    color: #5f6b78;
    font-weight: 700;
}

.umg-filter select, .umg-filter input {
    height: 34px;
    border: 1.5px solid #dde1ea;
    border-radius: 6px;
    padding: 0 10px;
    min-width: 170px;
    font-size: 13px;
    font-family: "Trebuchet MS", "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Tahoma, sans-serif;
    background: #fbfcfe;
    transition: border-color .12s ease, box-shadow .12s ease;
}

.umg-filter select:focus, .umg-filter input:focus {
    outline: none;
    border-color: var(--umg-blue);
    box-shadow: 0 0 0 3px rgba(47,125,209,0.14);
    background: #fff;
}

.umg-filter-wide input {
    min-width: 260px;
}

/* User Review table */
.umg-table-wrap {
    overflow: auto;
    max-height: 520px;
    border: 1px solid #e7e9f2;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(31,41,55,0.04);
}

.umg-table {
    width: 100%;
    border-collapse: collapse;
}

.umg-table th {
    position: sticky;
    top: 0;
    background: linear-gradient(180deg, #f3f6fc 0%, #eaeff8 100%);
    text-align: left;
    padding: 11px 10px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #445;
    border-bottom: 2px solid #dfe4ee;
    white-space: nowrap;
    z-index: 1;
}

.umg-table td {
    padding: 10px 10px;
    border-bottom: 1px solid #eef0f2;
    font-size: 13px;
    vertical-align: middle;
}

.umg-table tbody tr {
    transition: background-color .12s ease;
}

.umg-table tbody tr:nth-child(even) {
    background: #f8f9fd;
}

.umg-table tbody tr:hover {
    background: #eaf2ff;
}

.umg-table tbody tr.umg-row-checked {
    background: #dcebff;
    box-shadow: inset 3px 0 0 var(--umg-blue);
}

.umg-username {
    font-weight: 700;
    color: var(--umg-ink);
}

.umg-subtext {
    display: block;
    font-size: 11px;
    color: #7b8490;
    margin-top: 1px;
}

.umg-comment-text {
    font-size: 12px;
    color: #4b5563;
    max-width: 240px;
    white-space: normal;
}

.umg-comment-tag {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .02em;
    color: #6b7280;
    background: #eef1f4;
    border-radius: 4px;
    padding: 1px 5px;
    margin-right: 5px;
    vertical-align: middle;
}

/* Status badges */
.umg-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}

.umg-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.umg-badge-danger {
    background: linear-gradient(90deg, #fde3e3, #fdeceb);
    color: #b42323;
}

.umg-badge-danger::before {
    background: #d94b4b;
}

.umg-badge-warning {
    background: linear-gradient(90deg, #fff2cf, #fff8e5);
    color: #8a6200;
}

.umg-badge-warning::before {
    background: #e0a721;
}

.umg-badge-ok {
    background: linear-gradient(90deg, #dff5ea, #eafaf1);
    color: #176b3a;
}

.umg-badge-ok::before {
    background: #22a06b;
}

.umg-badge-info {
    background: linear-gradient(90deg, #e2eefc, #edf5fd);
    color: #175a9d;
}

.umg-badge-info::before {
    background: #2f7dd1;
}

.umg-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    flex-wrap: wrap;
    gap: 10px;
}

.umg-results-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.umg-results-header span.umg-match-count {
    color: #6b7280;
    font-size: 12px;
    font-weight: 600;
}

.umg-toolbar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* Modals (Disable/Flag, Approve, Reject, Approval Requests, Audit Log,
   Change Details, Settings) share one backdrop + header style */
.umg-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(30,20,60,0.45);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(1px);
}

.umg-modal-backdrop.umg-open {
    display: flex;
    animation: umg-fade-in .12s ease;
}

@keyframes umg-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}

.umg-modal {
    background: #fff;
    border-radius: 12px;
    padding: 22px;
    width: 440px;
    max-width: 90vw;
    box-shadow: 0 20px 55px rgba(31,20,70,0.30);
    animation: umg-pop-in .14s ease;
    position: relative;
    overflow: hidden;
}

.umg-modal-wide {
    width: 560px;
}

.umg-modal-xwide {
    width: 760px;
}

@keyframes umg-pop-in {
    from { transform: scale(.96); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.umg-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    background: var(--umg-modal-header-bg);
    margin: -22px -22px 16px -22px;
    padding: 16px 22px;
    border-radius: 12px 12px 0 0;
}

.umg-modal-header h3 {
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    text-transform: none;
}

.umg-modal-header .umg-modal-close-x {
    position: static;
    color: #fff;
    font-size: 20px;
}

.umg-modal-header .umg-modal-close-x:hover {
    background: rgba(255,255,255,0.18);
    color: #fff;
}

.umg-modal label {
    font-size: 12px;
    font-weight: 700;
    color: #4b5563;
}

.umg-modal textarea, .umg-modal input[type=text], .umg-modal input[type=date] {
    width: 100%;
    box-sizing: border-box;
    margin-top: 6px;
    margin-bottom: 14px;
    padding: 9px;
    border: 1.5px solid #dde1ea;
    border-radius: 6px;
    font-size: 13px;
    background: #fbfcfe;
}

.umg-modal textarea:focus, .umg-modal input:focus {
    outline: none;
    border-color: var(--umg-blue);
    box-shadow: 0 0 0 3px rgba(47,125,209,0.14);
}

.umg-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}

.umg-modal-subtitle {
    font-size: 12px;
    color: #8a94a3;
    margin: 0 0 16px 0;
}

.umg-modal-close-x {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    color: #9aa3b2;
    line-height: 1;
    padding: 2px 6px;
    border-radius: 4px;
}

.umg-row-hidden {
    display: none !important;
}

/* Buttons — base style is a readable light background + dark text. The
   pill / translucent-white style is scoped ONLY to
   "button.umg-btn.umg-btn-header" above, so Cancel/Reset/Close/Clear
   buttons elsewhere (on white panels/modals) stay visible instead of
   rendering as white-on-white. */
button.umg-btn {
    height: 34px;
    border: 1.5px solid #dde1ea;
    background: #fff;
    border-radius: 7px;
    padding: 0 16px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    font-family: "Trebuchet MS", "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Tahoma, sans-serif;
    color: #374151;
    transition: background-color .12s ease, border-color .12s ease, box-shadow .12s ease, transform .1s ease;
}

button.umg-btn:hover {
    background: #f3f5fb;
    border-color: #c3c9d6;
    box-shadow: 0 3px 8px rgba(31,41,55,0.08);
}

button.umg-btn:active {
    transform: translateY(1px);
}

button.umg-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

button.umg-btn-danger {
    background: #fff;
    border-color: var(--umg-red-dark);
    color: #f4000b;
    box-shadow: 0 4px 12px rgba(209,58,73,0.30);
}

button.umg-btn-danger:hover {
    filter: brightness(1.05);
    box-shadow: 0 6px 16px rgba(209,58,73,0.40);
}

button.umg-btn-primary {
    background: linear-gradient(135deg, var(--umg-indigo), var(--umg-blue));
    border-color: var(--umg-indigo-dark);
    color: #fff;
    box-shadow: 0 4px 12px rgba(79,95,237,0.30);
}

button.umg-btn-primary:hover {
    filter: brightness(1.06);
    box-shadow: 0 6px 16px rgba(79,95,237,0.40);
}

button.umg-btn-ghost {
    background: #fff;
}

button.umg-btn-sm {
    height: 27px;
    padding: 0 11px;
    font-size: 13px;
}

.umg-btn-icon {
    margin-right: -2px;
    margin-right: -5px;
}

.umg-empty-state {
    text-align: center;
    padding: 34px 10px;
    color: #8a94a3;
    font-size: 13px;
}

.umg-empty-state .umg-empty-icon {
    font-size: 30px;
    display: block;
    margin-bottom: 8px;
    opacity: .55;
}

/* Audit Log filter row + structured table */
.umg-audit-filter-row {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.umg-audit-filter-row input, .umg-audit-filter-row select {
    height: 32px;
    border: 1.5px solid #dde1ea;
    border-radius: 20px;
    padding: 0 12px;
    font-size: 12.5px;
    background: #fbfcfe;
}

.umg-audit-filter-row input:focus, .umg-audit-filter-row select:focus {
    outline: none;
    border-color: var(--umg-blue);
    box-shadow: 0 0 0 3px rgba(47,125,209,0.14);
}

.umg-audit-search {
    min-width: 200px;
    flex: 1;
}

.umg-audit-table-wrap {
    max-height: 55vh;
    overflow: auto;
    border: 1px solid #eef0f2;
    border-radius: 8px;
}

.umg-audit-table {
    width: 100%;
    border-collapse: collapse;
}

.umg-audit-table th {
    position: sticky;
    top: 0;
    background: #f6f7fb;
    text-align: left;
    padding: 9px 12px;
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #6b7280;
    border-bottom: 2px solid #e7e9f2;
    white-space: nowrap;
}

.umg-audit-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #eef0f2;
    font-size: 12.5px;
    vertical-align: top;
}

.umg-audit-table tbody tr:hover {
    background: #f7faff;
}

.umg-audit-time {
    color: #6b7280;
    white-space: nowrap;
    font-size: 12px;
}

.umg-audit-actor {
    font-weight: 700;
    color: var(--umg-ink);
}

.umg-audit-actor-sub {
    color: #8a94a3;
    font-size: 11px;
}

.umg-audit-target {
    color: #374151;
}

.umg-audit-comment {
    color: #4b5563;
    max-width: 260px;
}

.umg-audit-empty-row td {
    text-align: center;
    color: #8a94a3;
    padding: 24px;
}

/* Approvers picker: chip-based "search & select" multiselect */
.umg-ms {
    position: relative;
}

.umg-ms-box {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 40px;
    border: 1.5px solid #dde1ea;
    border-radius: 8px;
    padding: 5px 8px;
    background: #fbfcfe;
    cursor: text;
    transition: border-color .12s ease, box-shadow .12s ease;
}

.umg-ms-box:focus-within {
    border-color: var(--umg-blue);
    box-shadow: 0 0 0 3px rgba(47,125,209,0.14);
    background: #fff;
}

.umg-ms-icon {
    color: #8a94a3;
    font-size: 13px;
    padding: 0 2px 0 4px;
}

.umg-ms-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #eaf2fc, #dcebff);
    border: 1px solid #bfd8f7;
    color: #175a9d;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 6px 3px 6px;
    border-radius: 16px;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(47,125,209,0.15);
}

.umg-ms-chip-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--umg-indigo), var(--umg-blue));
    color: #fff;
    font-size: 9px;
    font-weight: 800;
}

.umg-ms-chip-remove {
    cursor: pointer;
    color: #5f86ab;
    font-weight: 700;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    font-size: 13px;
}

.umg-ms-chip-remove:hover {
    background: #bfd8f7;
    color: #123a5c;
}

.umg-ms-input {
    border: none;
    outline: none;
    flex: 1;
    min-width: 140px;
    font-size: 13px;
    height: 24px;
    background: transparent;
}

.umg-ms-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #dde1ea;
    border-radius: 8px;
    box-shadow: 0 10px 26px rgba(31,41,55,0.16);
    max-height: 220px;
    overflow-y: auto;
    z-index: 30;
    display: none;
}

.umg-ms-dropdown.umg-open {
    display: block;
}

.umg-ms-option {
    padding: 9px 12px;
    font-size: 13px;
    cursor: pointer;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

.umg-ms-option-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: linear-gradient(135deg, #cfe1f7, #eaf2fc);
    color: #175a9d;
    font-size: 10px;
    font-weight: 800;
    flex-shrink: 0;
}

.umg-ms-option-text {
    flex: 1;
}

.umg-ms-option .umg-ms-option-sub {
    color: #8a94a3;
    font-size: 11px;
}

.umg-ms-option:hover, .umg-ms-option.umg-ms-active {
    background: #eaf2fc;
    color: #175a9d;
}

.umg-ms-empty {
    padding: 10px 12px;
    font-size: 12px;
    color: #8a94a3;
}

/* "Change Details" popup meta card */
.detail-card {
    background: #fff;
    border: 1px solid #e0e4ef;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 8px;
    font-size: 12.5px;
}

.detail-card p {
    margin: 4px 0;
}

/* Developer footer */
.developer-footer {
    margin: 30px 0 10px;
    padding-top: 12px;
    border-top: 1px solid #dcdcdc;
    text-align: center;
    font-family: "Trebuchet MS", Tahoma, Arial, sans-serif;
    font-size: 12px;
    color: #666;
}

.developer-footer strong {
    color: #1a3a5c;
}
</style>

<div class="umg-cards">
	<div class="umg-card">
		<div class="umg-card-header">
			<span class="umg-card-icon">&#128101;</span>
			<span class="umg-card-title"><?= _('Total Users') ?></span>
		</div>
		<div class="umg-card-value"><?= umg_esc($summary['total']) ?></div>
	</div>
	<div class="umg-card umg-accent-danger">
		<div class="umg-card-header">
			<span class="umg-card-icon">&#128683;</span>
			<span class="umg-card-title"><?= _('Never Logged In') ?></span>
		</div>
		<div class="umg-card-value"><?= umg_esc($summary['never_logged_in']) ?></div>
	</div>
	<div class="umg-card umg-accent-danger">
		<div class="umg-card-header">
			<span class="umg-card-icon">&#9203;</span>
			<span class="umg-card-title"><?= _('Inactive Over Threshold') ?></span>
		</div>
		<div class="umg-card-value"><?= umg_esc($summary['inactive_over_threshold']) ?></div>
	</div>
	<div class="umg-card umg-accent-warning">
		<div class="umg-card-header">
			<span class="umg-card-icon">&#9888;</span>
			<span class="umg-card-title"><?= _('Recommended Disable') ?></span>
		</div>
		<div class="umg-card-value"><?= umg_esc($summary['recommended_disable']) ?></div>
	</div>
	<div class="umg-card umg-accent-purple" id="umg-pending-card" style="cursor:pointer;" title="<?= _('View pending approvals') ?>">
		<div class="umg-card-header">
			<span class="umg-card-icon">&#9203;</span>
			<span class="umg-card-title"><?= _('Pending Approvals') ?></span>
		</div>
		<div class="umg-card-value"><?= umg_esc($pending_count) ?></div>
	</div>
</div>

<div class="umg-panel">
	<h2><span class="umg-h2-icon">&#128269;</span><?= _('Filter Users') ?></h2>
	<div class="umg-filter-row">
		<div class="umg-filter umg-filter-wide">
			<label><?= _('User') ?></label>
			<input type="text" id="umg-filter-username" placeholder="<?= _('Username') ?>">
		</div>
		<div class="umg-filter">
			<label><?= _('Activity') ?></label>
			<select id="umg-filter-activity">
				<option value=""><?= _('All Users') ?></option>
				<option value="never_logged_in"><?= _('Never Logged In') ?></option>
				<option value="inactive"><?= _('Inactive') ?></option>
				<option value="active"><?= _('Active') ?></option>
				<option value="new_account"><?= _('New Account') ?></option>
				<option value="already_disabled"><?= _('Already Disabled') ?></option>
			</select>
		</div>
		<div class="umg-filter">
			<label><?= _('Recommendation') ?></label>
			<select id="umg-filter-recommendation">
				<option value=""><?= _('All') ?></option>
				<option value="disable"><?= _('Disable') ?></option>
				<option value="no_action"><?= _('No Action') ?></option>
			</select>
		</div>
		<button type="button" class="umg-btn" id="umg-filter-reset"><?= _('Reset') ?></button>
	</div>
</div>

<div class="umg-panel">
	<div class="umg-results-header">
		<div class="umg-results-header-left">
			<h2 style="margin:0;"><span class="umg-h2-icon">&#128203;</span><?= _('User Review') ?></h2>
			<span class="umg-match-count" id="umg-match-count"></span>
		</div>
		<div class="umg-toolbar">
			<button type="button" class="umg-btn" id="umg-flag-selected">&#9873; <?= _('Request for Approval') ?></button>
			<button type="button" class="umg-btn umg-btn-danger" id="umg-disable-selected">&#128683; <?= _('Disable User/s') ?></button>
		</div>
	</div>

	<div class="umg-table-wrap">
		<table class="umg-table" id="umg-table">
			<thead>
				<tr>
					<th><input type="checkbox" id="umg-select-all"></th>
					<th><?= _('User') ?></th>
					<th><?= _('Account Created') ?></th>
					<th><?= _('Last Login') ?></th>
					<th><?= _('Account Age') ?></th>
					<th><?= _('Inactive For') ?></th>
					<th><?= _('Activity') ?></th>
					<th><?= _('Recommendation') ?></th>
					<th><?= _('Comment') ?></th>
					<th><?= _('Action') ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($data['users'] as $user): ?>
					<?php
					$creation_str = $user['creation_clock'] !== null
						? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['creation_clock'])
						: _('Not found');
					$login_str = $user['last_login_clock'] !== null
						? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['last_login_clock'])
						: _('Never');
					$account_age = $user['creation_age_days'] !== null ? $user['creation_age_days'] . ' ' . _('days') : '—';
					$inactive_for = $user['last_login_age_days'] !== null ? $user['last_login_age_days'] . ' ' . _('days') : '—';

					$activity_class = 'umg-badge-ok';
					$activity_label = _('Active');
					if ($user['reason'] === 'already_disabled') {
						$activity_class = 'umg-badge-info';
						$activity_label = _('Already Disabled');
					}
					elseif ($user['reason'] === 'never_logged_in') {
						$activity_class = 'umg-badge-danger';
						$activity_label = _('Never Logged In');
					}
					elseif ($user['reason'] === 'inactive') {
						$activity_class = 'umg-badge-danger';
						$activity_label = _('Inactive');
					}
					elseif ($user['reason'] === 'new_account') {
						$activity_class = 'umg-badge-info';
						$activity_label = _('New Account');
					}

					if ($user['pending_approval']) {
						$rec_class = 'umg-badge-warning';
						$rec_label = _('Pending Approval');
					}
					elseif ($user['reason'] === 'already_disabled') {
						$rec_class = 'umg-badge-info';
						$rec_label = _('Already Disabled');
					}
					elseif ($user['recommendation'] === 'disable') {
						$rec_class = 'umg-badge-danger';
						$rec_label = _('Disable');
					}
					else {
						$rec_class = 'umg-badge-ok';
						$rec_label = _('No Action');
					}

					$can_act = $user['recommendation'] === 'disable' && !$user['pending_approval'];

					if ($user['pending_approval']) {
						$comment_text = trim((string) ($user['pending_comment'] ?? ''));
						$comment_tag = $action_labels['flag'];
					}
					else {
						$comment_text = trim((string) ($user['disable_comment'] ?? ''));
						$comment_tag = $action_labels[$user['last_action'] ?? ''] ?? '';
					}

					$comment_csv = $comment_text;
					if ($comment_text !== '' && $comment_tag !== '') {
						$comment_csv = '[' . $comment_tag . '] ' . $comment_text;
					}
					if ($comment_text !== '' && !empty($user['disabled_by']) && !$user['pending_approval']) {
						$comment_csv .= ' - ' . $user['disabled_by'];
						if (!empty($user['disabled_at'])) {
							$comment_csv .= ' (' . zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['disabled_at']) . ')';
						}
					}
					?>
					<tr data-userid="<?= umg_esc($user['userid']) ?>" data-username="<?= umg_esc(mb_strtolower($user['username'])) ?>"
						data-activity="<?= umg_esc($user['reason']) ?>" data-recommendation="<?= umg_esc($user['recommendation']) ?>">
						<td><?php if ($can_act): ?><input type="checkbox" class="umg-row-checkbox"><?php endif; ?></td>
						<td>
							<div class="umg-username"><?= umg_esc($user['username']) ?></div>
							<div class="umg-subtext"><?= _('User ID:') ?> <?= umg_esc($user['userid']) ?></div>
						</td>
						<td><?= umg_esc($creation_str) ?></td>
						<td><?= umg_esc($login_str) ?></td>
						<td><?= umg_esc($account_age) ?></td>
						<td><?= umg_esc($inactive_for) ?></td>
						<td><span class="umg-badge <?= $activity_class ?>"><?= umg_esc($activity_label) ?></span></td>
						<td><span class="umg-badge <?= $rec_class ?>"><?= umg_esc($rec_label) ?></span></td>
						<td class="umg-comment-text" data-csv-comment="<?= umg_esc($comment_csv) ?>">
							<?php if ($comment_text !== ''): ?>
								<?php if ($comment_tag !== ''): ?><span class="umg-comment-tag"><?= umg_esc($comment_tag) ?></span><?php endif; ?>
								<?= umg_esc($comment_text) ?>
							<?php else: ?>
								-
							<?php endif; ?>
						</td>
						<td>
							<?php if ($can_act): ?>
							<button type="button" class="umg-btn umg-btn-danger umg-btn-sm umg-row-disable-btn" data-userid="<?= umg_esc($user['userid']) ?>"><?= _('Disable') ?></button>
							<?php else: ?>
							—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if (!$data['users']): ?>
		<div class="umg-empty-state"><span class="umg-empty-icon">&#128100;</span><?= _('No users found.') ?></div>
		<?php endif; ?>
	</div>
	<div class="umg-footer" style="margin-top:12px;">
		<div class="umg-card-title" id="umg-footer-info" style="text-transform:none;margin-bottom:0;font-weight:600;font-size:12px;color:#6b7280;"></div>
	</div>
</div>

<!-- Disable / Flag modal — also reused by the toolbar "Request for Approval" button. -->
<div class="umg-modal-backdrop" id="umg-modal-backdrop">
	<div class="umg-modal">
		<div class="umg-modal-header">
			<h3>&#128683; <?= _('Disable / Flag Users') ?></h3>
			<button type="button" class="umg-modal-close-x" id="umg-modal-close-x">&times;</button>
		</div>
		<div class="umg-card-title" id="umg-modal-userlist" style="text-transform:none;margin-bottom:12px;"></div>
		<label><?= _('Request No. / Comment (required to disable immediately, optional to flag)') ?></label>
		<textarea rows="3" id="umg-modal-comment"></textarea>
		<div class="umg-modal-actions">
			<button type="button" class="umg-btn" id="umg-modal-cancel"><?= _('Cancel') ?></button>
			<button type="button" class="umg-btn" id="umg-modal-flag"><?= _('Flag for Approval') ?></button>
			<button type="button" class="umg-btn umg-btn-danger" id="umg-modal-confirm"><?= _('Disable Now') ?></button>
		</div>
	</div>
</div>

<!-- Approve modal -->
<div class="umg-modal-backdrop" id="umg-approve-modal-backdrop">
	<div class="umg-modal">
		<div class="umg-modal-header">
			<h3>&#9989; <?= _('Approve & Disable') ?></h3>
			<button type="button" class="umg-modal-close-x" id="umg-approve-close-x">&times;</button>
		</div>
		<div class="umg-card-title" id="umg-approve-modal-userlist" style="text-transform:none;margin-bottom:12px;"></div>
		<label><?= _('Approval Comment (optional, added to the requester\'s comment)') ?></label>
		<textarea rows="3" id="umg-approve-comment"></textarea>
		<div class="umg-modal-actions">
			<button type="button" class="umg-btn" id="umg-approve-cancel"><?= _('Cancel') ?></button>
			<button type="button" class="umg-btn umg-btn-primary" id="umg-approve-confirm"><?= _('Approve & Disable') ?></button>
		</div>
	</div>
</div>

<!-- Reject modal -->
<div class="umg-modal-backdrop" id="umg-reject-modal-backdrop">
	<div class="umg-modal">
		<div class="umg-modal-header">
			<h3>&#10060; <?= _('Reject Request') ?></h3>
			<button type="button" class="umg-modal-close-x" id="umg-reject-close-x">&times;</button>
		</div>
		<label><?= _('Reason (optional)') ?></label>
		<textarea rows="3" id="umg-reject-comment"></textarea>
		<div class="umg-modal-actions">
			<button type="button" class="umg-btn" id="umg-reject-cancel"><?= _('Cancel') ?></button>
			<button type="button" class="umg-btn umg-btn-danger" id="umg-reject-confirm"><?= _('Reject') ?></button>
		</div>
	</div>
</div>

<!-- Approval Requests modal — opened via the header button or the summary card. -->
<div class="umg-modal-backdrop" id="umg-pending-modal-backdrop">
	<div class="umg-modal umg-modal-xwide">
		<div class="umg-modal-header">
			<h3>&#9203; <?= _('Pending for Approval') ?></h3>
			<button type="button" class="umg-modal-close-x" id="umg-pending-modal-close">&times;</button>
		</div>

		<?php if ($pending_queue): ?>
		<?php foreach ($pending_queue as $entry): ?>
		<div class="umg-approval-item">
			<div class="umg-approval-meta">
				<span class="umg-username"><?= umg_esc($entry['username']) ?></span>
				<span class="umg-subtext-inline">(<?= _('User ID:') ?> <?= umg_esc($entry['userid']) ?>)</span>
				<span class="umg-approval-dot">&middot;</span>
				<span class="umg-approval-flagged-inline">
					<?= _('Flagged by') ?> <strong><?= umg_esc($entry['flagged_by']) ?></strong>
					<?php if (!empty($entry['flagged_at'])): ?>
						&middot; <?= umg_esc(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $entry['flagged_at'])) ?>
					<?php endif; ?>
				</span>
				<?php if (!empty($entry['comment'])): ?>
				<span class="umg-approval-comment-inline" title="<?= umg_esc($entry['comment']) ?>">&#128172; <?= umg_esc($entry['comment']) ?></span>
				<?php endif; ?>
			</div>
			<div class="umg-approval-actions">
				<button type="button" class="umg-btn umg-btn-sm umg-btn-ghost umg-reject-btn"
					data-index="<?= umg_esc($entry['queue_index']) ?>" data-username="<?= umg_esc($entry['username']) ?>">
					<?= _('Reject') ?>
				</button>
				<button type="button" class="umg-btn umg-btn-sm umg-btn-primary umg-approve-btn"
					data-index="<?= umg_esc($entry['queue_index']) ?>" data-username="<?= umg_esc($entry['username']) ?>">
					<?= _('Approve & Disable') ?>
				</button>
			</div>
		</div>
		<?php endforeach; ?>
		<?php else: ?>
		<div class="umg-empty-state"><span class="umg-empty-icon">&#9203;</span><?= _('No pending approvals right now.') ?></div>
		<?php endif; ?>
	</div>
</div>

<!-- Audit Log modal: structured table (Time / Actor / Action / Target User / Comment)
     with a "Details" button for long comments, closes on Esc or the × in the header. -->
<div class="umg-modal-backdrop" id="umg-audit-modal-backdrop">
	<div class="umg-modal umg-modal-xwide">
		<div class="umg-modal-header">
			<h3>&#128337; <?= _('Audit Log') ?></h3>
			<button type="button" class="umg-modal-close-x" id="umg-audit-modal-close">&times;</button>
		</div>

		<?php if ($activity_log): ?>
		<div class="umg-audit-filter-row">
			<input type="text" id="umg-audit-search" class="umg-audit-search" placeholder="<?= _('Search actor / user / comment...') ?>">
			<select id="umg-audit-action-filter">
				<option value=""><?= _('All Actions') ?></option>
				<?php foreach ($action_labels as $key => $label): ?>
				<option value="<?= umg_esc($key) ?>"><?= umg_esc($label) ?></option>
				<?php endforeach; ?>
			</select>
			<input type="date" id="umg-audit-from-date" title="<?= _('From date') ?>">
			<input type="date" id="umg-audit-to-date" title="<?= _('To date') ?>">
			<button type="button" class="umg-btn umg-btn-sm" id="umg-audit-clear">&times; <?= _('Clear') ?></button>
		</div>

		<div class="umg-audit-table-wrap">
			<table class="umg-audit-table" id="umg-audit-table">
				<thead>
					<tr>
						<th><?= _('Time') ?></th>
						<th><?= _('Actor') ?></th>
						<th><?= _('Action') ?></th>
						<th><?= _('Target User') ?></th>
						<th><?= _('Comment') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($activity_log as $entry): ?>
						<?php
						$badge_class = $action_classes[$entry['action'] ?? ''] ?? 'umg-badge-info';
						$badge_label = $action_labels[$entry['action'] ?? ''] ?? ($entry['action'] ?? '');
						$comment_full = trim((string) ($entry['comment'] ?? ''));
						$is_long = mb_strlen($comment_full) > UMG_LOG_COMMENT_TRUNCATE;
						$comment_short = $is_long ? (mb_substr($comment_full, 0, UMG_LOG_COMMENT_TRUNCATE) . '…') : $comment_full;
						$search_blob = mb_strtolower(($entry['actor'] ?? '') . ' ' . ($entry['username'] ?? '') . ' ' . $comment_full);
						?>
						<tr data-action="<?= umg_esc($entry['action'] ?? '') ?>" data-clock="<?= umg_esc($entry['clock'] ?? 0) ?>" data-search="<?= umg_esc($search_blob) ?>">
							<td class="umg-audit-time"><?= !empty($entry['clock']) ? umg_esc(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $entry['clock'])) : '-' ?></td>
							<td>
								<div class="umg-audit-actor"><?= umg_esc($entry['actor'] ?? '-') ?></div>
							</td>
							<td><span class="umg-badge <?= $badge_class ?>"><?= umg_esc($badge_label) ?></span></td>
							<td class="umg-audit-target">
								<?= umg_esc($entry['username'] ?? '-') ?>
								<div class="umg-audit-actor-sub"><?= _('User ID:') ?> <?= umg_esc($entry['userid'] ?? '-') ?></div>
							</td>
							<td class="umg-audit-comment">
								<?php if ($comment_full === ''): ?>
									-
								<?php else: ?>
									<?= umg_esc($comment_short) ?>
									<?php if ($is_long): ?>
									<br>
									<button type="button" class="umg-btn umg-btn-sm umg-audit-details-btn" style="margin-top:4px;"
										data-full="<?= umg_esc($comment_full) ?>"
										data-meta="<?= umg_esc((!empty($entry['clock']) ? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $entry['clock']) : '-') . ' · ' . ($entry['actor'] ?? '-') . ' · ' . $badge_label . ' · ' . ($entry['username'] ?? '-')) ?>">
										<?= _('Details') ?>
									</button>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php else: ?>
		<div class="umg-empty-state"><span class="umg-empty-icon">&#128203;</span><?= _('No activity recorded yet.') ?></div>
		<?php endif; ?>
	</div>
</div>

<!-- Audit Log "Change Details" popup — reused for any truncated comment, closes on Esc. -->
<div class="umg-modal-backdrop" id="umg-log-details-modal-backdrop">
	<div class="umg-modal">
		<div class="umg-modal-header">
			<h3>&#128203; <?= _('Change Details') ?></h3>
			<button type="button" class="umg-modal-close-x" id="umg-log-details-close-x">&times;</button>
		</div>
		<div class="detail-card" id="umg-log-details-meta-card">
			<p><strong id="umg-log-details-meta"></strong></p>
		</div>
		<div id="umg-log-details-text" style="white-space:pre-wrap;background:#f6f7f9;border-radius:6px;padding:10px 12px;font-size:13px;color:#374151;"></div>
		<div class="umg-modal-actions" style="margin-top:16px;">
			<button type="button" class="umg-btn" id="umg-log-details-close"><?= _('Close') ?></button>
		</div>
	</div>
</div>

<!-- Settings modal — houses the full "Inactivity Policy (configurable)" panel. -->
<div class="umg-modal-backdrop" id="umg-settings-modal-backdrop">
	<div class="umg-modal umg-modal-wide">
		<div class="umg-modal-header">
			<h3>&#9881; <?= _('Inactivity Policy Settings') ?></h3>
			<button type="button" class="umg-modal-close-x" id="umg-settings-close-x">&times;</button>
		</div>
		<div class="umg-modal-subtitle"><?= _('Controls which accounts are recommended for disabling, and who may approve disable requests.') ?></div>

		<div class="umg-filter-row">
			<div class="umg-filter">
				<label><?= _('Min. Account Age (days)') ?></label>
				<input type="number" min="0" id="umg-cfg-min-age" value="<?= umg_esc($config['min_account_age_days']) ?>">
			</div>
			<div class="umg-filter">
				<label><?= _('Inactivity Threshold (days)') ?></label>
				<input type="number" min="0" id="umg-cfg-threshold" value="<?= umg_esc($config['inactivity_threshold_days']) ?>">
			</div>
		</div>

		<div class="umg-filter" style="margin-top:14px;">
			<label><?= _('Approvers (Super Admins only; leave empty = any Super Admin)') ?></label>

			<div class="umg-ms" id="umg-approvers-ms">
				<div class="umg-ms-box" id="umg-approvers-box">
					<span class="umg-ms-icon">&#128269;</span>
					<input type="text" class="umg-ms-input" id="umg-approvers-input"
						placeholder="<?= _('Search Super Admins...') ?>"
						<?= !$superadmins ? 'disabled' : '' ?>>
				</div>
				<div class="umg-ms-dropdown" id="umg-approvers-dropdown"></div>
			</div>
			<script type="application/json" id="umg-superadmins-data"><?= json_encode($superadmins) ?></script>
			<script type="application/json" id="umg-configured-approvers-data"><?= json_encode($configured_approvers) ?></script>

			<span class="umg-subtext">
				<?= $superadmins
					? _('Type to search, click a name to add. Click × on a chip to remove.')
					: _('No Super Admin accounts found.') ?>
			</span>
		</div>

		<div class="umg-modal-actions" style="margin-top:18px;">
			<button type="button" class="umg-btn" id="umg-settings-close"><?= _('Close') ?></button>
			<button type="button" class="umg-btn umg-btn-primary" id="umg-cfg-save">&#128190; <?= _('Save Policy') ?></button>
		</div>
	</div>
</div>

<script>
(function() {
	var table = document.getElementById('umg-table');
	var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));

	function applyFilters() {
		var username = document.getElementById('umg-filter-username').value.trim().toLowerCase();
		var activity = document.getElementById('umg-filter-activity').value;
		var recommendation = document.getElementById('umg-filter-recommendation').value;
		var visible = 0;

		rows.forEach(function(row) {
			var matches = true;
			if (username && row.getAttribute('data-username').indexOf(username) === -1) matches = false;
			if (activity && row.getAttribute('data-activity') !== activity) matches = false;
			if (recommendation && row.getAttribute('data-recommendation') !== recommendation) matches = false;
			row.classList.toggle('umg-row-hidden', !matches);
			if (matches) visible++;
		});

		document.getElementById('umg-match-count').textContent = visible + ' ' + '<?= _('matching users') ?>';
		document.getElementById('umg-footer-info').textContent = '<?= _('Showing') ?> ' + visible + ' ' + '<?= _('of') ?>' + ' ' + rows.length + ' ' + '<?= _('users') ?>';
	}

	document.getElementById('umg-filter-username').addEventListener('input', applyFilters);
	document.getElementById('umg-filter-activity').addEventListener('change', applyFilters);
	document.getElementById('umg-filter-recommendation').addEventListener('change', applyFilters);
	document.getElementById('umg-filter-reset').addEventListener('click', function() {
		document.getElementById('umg-filter-username').value = '';
		document.getElementById('umg-filter-activity').value = '';
		document.getElementById('umg-filter-recommendation').value = '';
		applyFilters();
	});

	var selectAll = document.getElementById('umg-select-all');
	selectAll.addEventListener('change', function() {
		table.querySelectorAll('.umg-row-checkbox').forEach(function(cb) {
			var row = cb.closest('tr');
			if (!row.classList.contains('umg-row-hidden')) {
				cb.checked = selectAll.checked;
				row.classList.toggle('umg-row-checked', cb.checked);
			}
		});
	});

	table.addEventListener('change', function(e) {
		if (e.target.classList.contains('umg-row-checkbox')) {
			e.target.closest('tr').classList.toggle('umg-row-checked', e.target.checked);
		}
	});

	function getSelectedUserIds() {
		return Array.prototype.slice.call(table.querySelectorAll('.umg-row-checkbox:checked')).map(function(cb) {
			return cb.closest('tr').getAttribute('data-userid');
		});
	}

	// ---- Generic modal open/close + a single Esc handler that closes ----
	// ---- whichever modal is active. ----
	function openBackdrop(backdrop) { backdrop.classList.add('umg-open'); }
	function closeBackdrop(backdrop) { backdrop.classList.remove('umg-open'); }

	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' || e.keyCode === 27) {
			document.querySelectorAll('.umg-modal-backdrop.umg-open').forEach(closeBackdrop);
			var dd = document.getElementById('umg-approvers-dropdown');
			if (dd) dd.classList.remove('umg-open');
		}
	});

	// Disable / Flag modal (shared by row-level Disable button AND the toolbar
	// "Request for Approval" button, so a comment can always be added).
	var modalBackdrop = document.getElementById('umg-modal-backdrop');
	var modalUserlist = document.getElementById('umg-modal-userlist');
	var modalComment = document.getElementById('umg-modal-comment');
	var pendingUserIds = [];

	function openModal(userIds) {
		pendingUserIds = userIds;
		modalUserlist.textContent = userIds.length + ' ' + '<?= _('users selected') ?>';
		modalComment.value = '';
		openBackdrop(modalBackdrop);
	}
	function closeModal() {
		closeBackdrop(modalBackdrop);
		pendingUserIds = [];
	}
	document.getElementById('umg-modal-cancel').addEventListener('click', closeModal);
	document.getElementById('umg-modal-close-x').addEventListener('click', closeModal);

	document.getElementById('umg-disable-selected').addEventListener('click', function() {
		var ids = getSelectedUserIds();
		if (!ids.length) { alert('<?= _('Select at least one user.') ?>'); return; }
		openModal(ids);
	});
	document.getElementById('umg-flag-selected').addEventListener('click', function() {
		var ids = getSelectedUserIds();
		if (!ids.length) { alert('<?= _('Select at least one user.') ?>'); return; }
		openModal(ids);
	});
	table.addEventListener('click', function(e) {
		if (e.target.classList.contains('umg-row-disable-btn')) {
			openModal([e.target.getAttribute('data-userid')]);
		}
	});

	function submitAction(params) {
		var body = new URLSearchParams();
		(params.userids || []).forEach(function(id) { body.append('userids[]', id); });
		if (params.queue_index !== undefined) body.append('queue_index', params.queue_index);
		body.append('mode', params.mode);
		body.append('comment', params.comment || '');

		return fetch('zabbix.php?action=user.policy.execute', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				alert(data.message || (data.success ? '<?= _('Done.') ?>' : '<?= _('Failed.') ?>'));
				if (data.success) window.location.reload();
			})
			.catch(function() { alert('<?= _('Request failed.') ?>'); });
	}

	document.getElementById('umg-modal-confirm').addEventListener('click', function() {
		submitAction({ userids: pendingUserIds, mode: 'immediate', comment: modalComment.value.trim() });
		closeModal();
	});
	document.getElementById('umg-modal-flag').addEventListener('click', function() {
		submitAction({ userids: pendingUserIds, mode: 'flag', comment: modalComment.value.trim() });
		closeModal();
	});

	// Approve modal
	var approveBackdrop = document.getElementById('umg-approve-modal-backdrop');
	var approveUserlist = document.getElementById('umg-approve-modal-userlist');
	var approveComment = document.getElementById('umg-approve-comment');
	var approveIndex = null;

	document.querySelectorAll('.umg-approve-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			approveIndex = btn.getAttribute('data-index');
			approveUserlist.textContent = btn.getAttribute('data-username');
			approveComment.value = '';
			openBackdrop(approveBackdrop);
		});
	});
	document.getElementById('umg-approve-cancel').addEventListener('click', function() { closeBackdrop(approveBackdrop); });
	document.getElementById('umg-approve-close-x').addEventListener('click', function() { closeBackdrop(approveBackdrop); });
	document.getElementById('umg-approve-confirm').addEventListener('click', function() {
		submitAction({ mode: 'approve', queue_index: approveIndex, comment: approveComment.value.trim() });
		closeBackdrop(approveBackdrop);
	});

	// Reject modal
	var rejectBackdrop = document.getElementById('umg-reject-modal-backdrop');
	var rejectComment = document.getElementById('umg-reject-comment');
	var rejectIndex = null;

	document.querySelectorAll('.umg-reject-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			rejectIndex = btn.getAttribute('data-index');
			rejectComment.value = '';
			openBackdrop(rejectBackdrop);
		});
	});
	document.getElementById('umg-reject-cancel').addEventListener('click', function() { closeBackdrop(rejectBackdrop); });
	document.getElementById('umg-reject-close-x').addEventListener('click', function() { closeBackdrop(rejectBackdrop); });
	document.getElementById('umg-reject-confirm').addEventListener('click', function() {
		submitAction({ mode: 'reject', queue_index: rejectIndex, comment: rejectComment.value.trim() });
		closeBackdrop(rejectBackdrop);
	});

	// Approval Requests modal wiring — opened from the header button OR the summary card.
	var pendingBackdrop = document.getElementById('umg-pending-modal-backdrop');
	document.getElementById('umg-pending-btn').addEventListener('click', function() {
		openBackdrop(pendingBackdrop);
	});
	document.getElementById('umg-pending-modal-close').addEventListener('click', function() {
		closeBackdrop(pendingBackdrop);
	});
	var pendingCard = document.getElementById('umg-pending-card');
	if (pendingCard) {
		pendingCard.addEventListener('click', function() { openBackdrop(pendingBackdrop); });
	}

	// Audit Log modal wiring + client-side search/action/date filtering
	var auditBackdrop = document.getElementById('umg-audit-modal-backdrop');
	document.getElementById('umg-audit-log-btn').addEventListener('click', function() {
		openBackdrop(auditBackdrop);
	});
	document.getElementById('umg-audit-modal-close').addEventListener('click', function() {
		closeBackdrop(auditBackdrop);
	});

	var auditTable = document.getElementById('umg-audit-table');
	if (auditTable) {
		var auditRows = Array.prototype.slice.call(auditTable.querySelectorAll('tbody tr'));
		var auditSearch = document.getElementById('umg-audit-search');
		var auditActionFilter = document.getElementById('umg-audit-action-filter');
		var auditFromDate = document.getElementById('umg-audit-from-date');
		var auditToDate = document.getElementById('umg-audit-to-date');

		function applyAuditFilters() {
			var q = auditSearch.value.trim().toLowerCase();
			var action = auditActionFilter.value;
			var fromTs = auditFromDate.value ? new Date(auditFromDate.value + 'T00:00:00').getTime() / 1000 : null;
			var toTs = auditToDate.value ? new Date(auditToDate.value + 'T23:59:59').getTime() / 1000 : null;

			auditRows.forEach(function(row) {
				var matches = true;
				if (q && row.getAttribute('data-search').indexOf(q) === -1) matches = false;
				if (action && row.getAttribute('data-action') !== action) matches = false;
				var clock = parseInt(row.getAttribute('data-clock'), 10) || 0;
				if (fromTs !== null && clock < fromTs) matches = false;
				if (toTs !== null && clock > toTs) matches = false;
				row.classList.toggle('umg-row-hidden', !matches);
			});
		}

		auditSearch.addEventListener('input', applyAuditFilters);
		auditActionFilter.addEventListener('change', applyAuditFilters);
		auditFromDate.addEventListener('change', applyAuditFilters);
		auditToDate.addEventListener('change', applyAuditFilters);
		document.getElementById('umg-audit-clear').addEventListener('click', function() {
			auditSearch.value = '';
			auditActionFilter.value = '';
			auditFromDate.value = '';
			auditToDate.value = '';
			applyAuditFilters();
		});
	}

	// Audit Log "Details" popup for long comments
	var logDetailsBackdrop = document.getElementById('umg-log-details-modal-backdrop');
	var logDetailsMeta = document.getElementById('umg-log-details-meta');
	var logDetailsText = document.getElementById('umg-log-details-text');

	document.querySelectorAll('.umg-audit-details-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			logDetailsMeta.textContent = btn.getAttribute('data-meta') || '';
			logDetailsText.textContent = btn.getAttribute('data-full') || '';
			openBackdrop(logDetailsBackdrop);
		});
	});
	document.getElementById('umg-log-details-close').addEventListener('click', function() { closeBackdrop(logDetailsBackdrop); });
	document.getElementById('umg-log-details-close-x').addEventListener('click', function() { closeBackdrop(logDetailsBackdrop); });

	// Settings modal wiring
	var settingsBackdrop = document.getElementById('umg-settings-modal-backdrop');
	document.getElementById('umg-settings-btn').addEventListener('click', function() {
		openBackdrop(settingsBackdrop);
	});
	document.getElementById('umg-settings-close').addEventListener('click', function() {
		closeBackdrop(settingsBackdrop);
	});
	document.getElementById('umg-settings-close-x').addEventListener('click', function() {
		closeBackdrop(settingsBackdrop);
	});

	// ---------------------------------------------------------------------
	// Approvers chip picker (search-as-you-type + tags), living inside the
	// Settings modal. Mirrors the native Host multiselect UX.
	// ---------------------------------------------------------------------
	(function() {
		var allUsers = JSON.parse(document.getElementById('umg-superadmins-data').textContent || '[]');
		var preselected = JSON.parse(document.getElementById('umg-configured-approvers-data').textContent || '[]');

		var box = document.getElementById('umg-approvers-box');
		var input = document.getElementById('umg-approvers-input');
		var dropdown = document.getElementById('umg-approvers-dropdown');

		var selected = allUsers.filter(function(u) { return preselected.indexOf(u.username) !== -1; });

		function initials(name) {
			return (name || '?').trim().charAt(0).toUpperCase();
		}

		function isSelected(username) {
			return selected.some(function(u) { return u.username === username; });
		}

		function renderChips() {
			box.querySelectorAll('.umg-ms-chip').forEach(function(chip) { chip.remove(); });
			selected.forEach(function(u) {
				var chip = document.createElement('span');
				chip.className = 'umg-ms-chip';
				chip.setAttribute('data-username', u.username);
				chip.innerHTML = '<span class="umg-ms-chip-avatar"></span><span></span><span class="umg-ms-chip-remove">&times;</span>';
				chip.querySelector('.umg-ms-chip-avatar').textContent = initials(u.username);
				chip.querySelector('span:nth-child(2)').textContent = u.username;
				chip.querySelector('.umg-ms-chip-remove').addEventListener('click', function(e) {
					e.stopPropagation();
					selected = selected.filter(function(su) { return su.username !== u.username; });
					renderChips();
					renderDropdown(input.value);
				});
				box.insertBefore(chip, box.querySelector('.umg-ms-icon'));
			});
		}

		function renderDropdown(query) {
			query = (query || '').trim().toLowerCase();
			var matches = allUsers.filter(function(u) {
				return !isSelected(u.username) && u.username.toLowerCase().indexOf(query) !== -1;
			});

			dropdown.innerHTML = '';
			if (!matches.length) {
				var empty = document.createElement('div');
				empty.className = 'umg-ms-empty';
				empty.textContent = '<?= _('No matching Super Admins') ?>';
				dropdown.appendChild(empty);
			}
			else {
				matches.forEach(function(u) {
					var opt = document.createElement('div');
					opt.className = 'umg-ms-option';
					opt.innerHTML = '<span class="umg-ms-option-avatar"></span><span class="umg-ms-option-text"></span><span class="umg-ms-option-sub"></span>';
					opt.querySelector('.umg-ms-option-avatar').textContent = initials(u.username);
					opt.querySelector('.umg-ms-option-text').textContent = u.username;
					opt.querySelector('.umg-ms-option-sub').textContent = '<?= _('User ID:') ?> ' + u.userid;
					opt.addEventListener('click', function() {
						selected.push(u);
						input.value = '';
						renderChips();
						renderDropdown('');
						input.focus();
					});
					dropdown.appendChild(opt);
				});
			}
			dropdown.classList.add('umg-open');
		}

		input.addEventListener('focus', function() { renderDropdown(input.value); });
		input.addEventListener('input', function() { renderDropdown(input.value); });
		box.addEventListener('click', function() { input.focus(); });

		document.addEventListener('click', function(e) {
			if (!document.getElementById('umg-approvers-ms').contains(e.target)) {
				dropdown.classList.remove('umg-open');
			}
		});

		renderChips();

		// Exposed for the Save Policy handler below.
		window.__umgGetSelectedApprovers = function() {
			return selected.map(function(u) { return u.username; });
		};
	})();

	// Policy save — approvers are read from the chip picker, joined the same
	// way the backend already expects (comma-separated usernames).
	document.getElementById('umg-cfg-save').addEventListener('click', function() {
		var approvers = (window.__umgGetSelectedApprovers ? window.__umgGetSelectedApprovers() : []).join(',');

		var body = new URLSearchParams();
		body.append('min_account_age_days', document.getElementById('umg-cfg-min-age').value);
		body.append('inactivity_threshold_days', document.getElementById('umg-cfg-threshold').value);
		body.append('approvers', approvers);

		fetch('zabbix.php?action=user.policy.config', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				alert(data.message || (data.success ? '<?= _('Saved.') ?>' : '<?= _('Failed.') ?>'));
				if (data.success) window.location.reload();
			})
			.catch(function() { alert('<?= _('Request failed.') ?>'); });
	});

	// CSV export of currently-visible rows, including the resolved activity comment.
	function csvSafe(text) {
		text = String(text == null ? '' : text).trim();
		if (text === '' || text === '\u2014' || text === '\u2013' || text === '-') {
			return '-';
		}
		return text;
	}

	document.getElementById('umg-export-csv').addEventListener('click', function() {
		var header = ['Username', 'User ID', 'Account Created', 'Last Login', 'Account Age', 'Inactive For', 'Activity', 'Recommendation', 'Comment'];
		var lines = [header.join(',')];

		function csvField(text) {
			text = csvSafe(text);
			if (/,|"|\n/.test(text)) {
				text = '"' + text.replace(/"/g, '""') + '"';
			}
			return text;
		}

		rows.forEach(function(row) {
			if (row.classList.contains('umg-row-hidden')) return;
			var cells = row.querySelectorAll('td');
			var username = cells[1].querySelector('.umg-username').textContent.trim();
			var userid = row.getAttribute('data-userid');
			var commentCell = cells[8];
			var comment = commentCell ? commentCell.getAttribute('data-csv-comment') : '';
			var fields = [username, userid, cells[2].textContent.trim(), cells[3].textContent.trim(),
				cells[4].textContent.trim(), cells[5].textContent.trim(), cells[6].textContent.trim(),
				cells[7].textContent.trim(), comment];
			lines.push(fields.map(csvField).join(','));
		});

		var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = 'user_management_export.csv';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	});

	applyFilters();
})();
</script>

<div class="developer-footer">
	<strong>Developed By:</strong> Saumyajit Pramanik
</div>
