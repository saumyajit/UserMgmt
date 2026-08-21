<?php declare(strict_types = 1);

/**
 * @var CView $this
 * @var array $data
 */

$users = $data['users'];
$disable_days = $data['disable_days'];
$delete_days = $data['delete_days'];
?>

<form method="post" action="zabbix.php">
	<input type="hidden" name="action" value="user.policy">

	<div class="table-form">
		<div class="form-grid">
			<label for="disable_days">
				<?= _('Disable after days') ?>
			</label>

			<input
				type="number"
				id="disable_days"
				name="disable_days"
				value="<?= htmlspecialchars((string) $disable_days, ENT_QUOTES) ?>"
				min="1"
				max="3650"
			>

			<label for="delete_days">
				<?= _('Delete after days') ?>
			</label>

			<input
				type="number"
				id="delete_days"
				name="delete_days"
				value="<?= htmlspecialchars((string) $delete_days, ENT_QUOTES) ?>"
				min="2"
				max="3650"
			>
		</div>
	</div>

	<div class="table-scroll">
		<table class="list-table">
			<thead>
				<tr>
					<th>
						<input type="checkbox" id="select-all">
					</th>
					<th><?= _('User ID') ?></th>
					<th><?= _('Username') ?></th>
					<th><?= _('Name') ?></th>
					<th><?= _('Role') ?></th>
					<th><?= _('Created') ?></th>
					<th><?= _('Last activity') ?></th>
					<th><?= _('Current state') ?></th>
					<th><?= _('Recommendation') ?></th>
					<th><?= _('Reason') ?></th>
				</tr>
			</thead>

			<tbody>
			<?php if (!$users): ?>
				<tr>
					<td colspan="10">
						<?= _('No users were returned.') ?>
					</td>
				</tr>
			<?php endif; ?>

			<?php foreach ($users as $user): ?>
				<?php
				$selectable = in_array($user['action'], ['disable', 'delete'], true);
				$row_class = match ($user['action']) {
					'delete' => 'table-row-danger',
					'disable' => 'table-row-warning',
					default => ''
				};
				?>
				<tr class="<?= $row_class ?>">
					<td>
						<?php if ($selectable): ?>
							<input
								type="checkbox"
								class="user-select"
								name="selected[]"
								value="<?= htmlspecialchars($user['userid'], ENT_QUOTES) ?>"
								data-operation="<?= htmlspecialchars($user['action'], ENT_QUOTES) ?>"
							>
						<?php endif; ?>
					</td>

					<td><?= htmlspecialchars($user['userid'], ENT_QUOTES) ?></td>

					<td>
						<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>
					</td>

					<td>
						<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>
					</td>

					<td>
						<?= htmlspecialchars($user['role'], ENT_QUOTES) ?>
					</td>

					<td>
						<?= htmlspecialchars($user['created_text'], ENT_QUOTES) ?>
					</td>

					<td>
						<?= htmlspecialchars($user['last_activity_text'], ENT_QUOTES) ?>
					</td>

					<td>
						<?= $user['disabled'] ? _('Disabled') : _('Enabled') ?>
					</td>

					<td>
						<?= htmlspecialchars($user['decision'], ENT_QUOTES) ?>
					</td>

					<td>
						<?= htmlspecialchars($user['reason'], ENT_QUOTES) ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="form-buttons">
		<button type="submit" class="btn-alt">
			<?= _('Refresh preview') ?>
		</button>

		<button
			type="button"
			class="btn-alt js-execute"
			data-operation="disable"
		>
			<?= _('Disable selected') ?>
		</button>

		<button
			type="button"
			class="btn-alt js-execute"
			data-operation="delete"
		>
			<?= _('Delete selected') ?>
		</button>
	</div>
</form>

<div id="user-policy-modal" style="display: none;">
	<form method="post" action="zabbix.php">
		<input type="hidden" name="action" value="user.policy.execute">
		<input type="hidden" name="operation" id="modal-operation">
		<input type="hidden" name="confirm" value="1">
		<input type="hidden" name="dry_run" value="0">
		<div id="modal-selected"></div>

		<p id="modal-message"></p>

		<div class="form-buttons">
			<button type="submit" class="btn-alt">
				<?= _('Confirm') ?>
			</button>

			<button type="button" class="btn-alt js-close-modal">
				<?= _('Cancel') ?>
			</button>
		</div>
	</form>
</div>

<script>
(function () {
	'use strict';

	const selectAll = document.getElementById('select-all');

	if (selectAll) {
		selectAll.addEventListener('change', function () {
			document.querySelectorAll('.user-select').forEach(function (checkbox) {
				checkbox.checked = selectAll.checked;
			});
		});
	}

	document.querySelectorAll('.js-execute').forEach(function (button) {
		button.addEventListener('click', function () {
			const operation = button.dataset.operation;
			const selected = Array.from(document.querySelectorAll('.user-select:checked'));

			if (selected.length === 0) {
				alert('Select at least one user.');
				return;
			}

			const selectedForOperation = selected.filter(function (checkbox) {
				return checkbox.dataset.operation === operation;
			});

			if (selectedForOperation.length === 0) {
				alert('No selected users match the requested operation.');
				return;
			}

			const container = document.getElementById('modal-selected');
			container.innerHTML = '';

			selectedForOperation.forEach(function (checkbox) {
				const input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'selected[]';
				input.value = checkbox.value;
				container.appendChild(input);
			});

			document.getElementById('modal-operation').value = operation;
			document.getElementById('modal-message').textContent =
				'You are about to ' + operation + ' ' + selectedForOperation.length
				+ ' selected user(s). Continue?';

			document.getElementById('user-policy-modal').style.display = 'block';
		});
	});

	document.querySelector('.js-close-modal')?.addEventListener('click', function () {
		document.getElementById('user-policy-modal').style.display = 'none';
	});
})();
</script>
