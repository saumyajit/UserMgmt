<?php declare(strict_types = 1);

/** @var CView $this */
/** @var array $data */

$users = $data['users'] ?? [];
$disable_days = $data['disable_days'] ?? 45;
$delete_days = $data['delete_days'] ?? 90;
$can_edit = $data['can_edit'] ?? false;
$can_delete = $data['can_delete'] ?? false;
?>

<form method="post" action="zabbix.php">
	<input type="hidden" name="action" value="user.policy">

	<div class="table-form">
		<div class="form-grid">
			<label for="disable_days"><?= _('Disable after days') ?></label>
			<input type="number" id="disable_days" name="disable_days"
				value="<?= htmlspecialchars((string) $disable_days, ENT_QUOTES) ?>"
				min="1" max="3650">

			<label for="delete_days"><?= _('Delete after days') ?></label>
			<input type="number" id="delete_days" name="delete_days"
				value="<?= htmlspecialchars((string) $delete_days, ENT_QUOTES) ?>"
				min="2" max="3650">
		</div>
	</div>

	<div class="table-scroll">
		<table class="list-table">
			<thead>
				<tr>
					<th><input type="checkbox" id="select-all"></th>
					<th><?= _('User ID') ?></th>
					<th><?= _('Username') ?></th>
					<th><?= _('Name') ?></th>
					<th><?= _('Role') ?></th>
					<th><?= _('Created') ?></th>
					<th><?= _('Last activity') ?></th>
					<th><?= _('State') ?></th>
					<th><?= _('Recommendation') ?></th>
					<th><?= _('Reason') ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($users as $user): ?>
				<?php $selectable = $user['action'] !== 'none' && $user['action'] !== 'protected'; ?>
				<tr>
					<td>
						<?php if ($selectable): ?>
							<input type="checkbox" class="user-select"
								name="selected[]"
								value="<?= htmlspecialchars($user['userid'], ENT_QUOTES) ?>"
								data-operation="<?= htmlspecialchars($user['action'], ENT_QUOTES) ?>">
						<?php endif; ?>
					</td>
					<td><?= htmlspecialchars($user['userid'], ENT_QUOTES) ?></td>
					<td><?= htmlspecialchars($user['username'], ENT_QUOTES) ?></td>
					<td><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></td>
					<td><?= htmlspecialchars($user['role'], ENT_QUOTES) ?></td>
					<td><?= htmlspecialchars($user['created_text'], ENT_QUOTES) ?></td>
					<td><?= htmlspecialchars($user['last_activity_text'], ENT_QUOTES) ?></td>
					<td><?= $user['disabled'] ? _('Disabled') : _('Enabled') ?></td>
					<td><?= htmlspecialchars($user['decision'], ENT_QUOTES) ?></td>
					<td><?= htmlspecialchars($user['reason'], ENT_QUOTES) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="form-buttons">
		<button type="submit" class="btn-alt">
			<?= _('Refresh preview') ?>
		</button>
	</div>
</form>

<?php if ($can_edit): ?>
<form method="post" action="zabbix.php">
	<input type="hidden" name="action" value="user.policy.execute">
	<input type="hidden" name="operation" value="disable">
	<input type="hidden" name="confirm" value="1">
	<input type="hidden" name="dry_run" value="1">

	<?php foreach ($users as $user): ?>
		<?php if ($user['action'] === 'disable'): ?>
			<input type="hidden" name="selected[]" value="<?= htmlspecialchars($user['userid'], ENT_QUOTES) ?>">
		<?php endif; ?>
	<?php endforeach; ?>

	<button type="submit" class="btn-alt">
		<?= _('Run disable dry-run') ?>
	</button>
</form>
<?php endif; ?>

<?php if ($can_delete): ?>
<form method="post" action="zabbix.php">
	<input type="hidden" name="action" value="user.policy.execute">
	<input type="hidden" name="operation" value="delete">
	<input type="hidden" name="confirm" value="0">
	<input type="hidden" name="dry_run" value="1">

	<?php foreach ($users as $user): ?>
		<?php if ($user['action'] === 'delete'): ?>
			<input type="hidden" name="selected[]" value="<?= htmlspecialchars($user['userid'], ENT_QUOTES) ?>">
		<?php endif; ?>
	<?php endforeach; ?>

	<button type="submit" class="btn-alt">
		<?= _('Run delete dry-run') ?>
	</button>
</form>
<?php endif; ?>
