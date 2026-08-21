<?php declare(strict_types = 1);

/** @var CView $this */
/** @var array $data */

$processed = $data['processed'] ?? [];
$errors = $data['errors'] ?? [];
$operation = $data['operation'] ?? '';
$dry_run = (int) ($data['dry_run'] ?? 1);
?>

<?php if ($dry_run): ?>
	<div class="msg-good">
		<?= _('Dry-run completed. No changes were made.') ?>
	</div>
<?php else: ?>
	<div class="msg-good">
		<?= htmlspecialchars(sprintf(_('%s operation completed.'), ucfirst($operation)), ENT_QUOTES) ?>
	</div>
<?php endif; ?>

<?php if ($processed): ?>
	<table class="list-table">
		<thead>
			<tr>
				<th><?= _('User ID') ?></th>
				<th><?= _('Username') ?></th>
				<th><?= _('Operation') ?></th>
				<th><?= _('Status') ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($processed as $item): ?>
			<tr>
				<td><?= htmlspecialchars($item['userid'], ENT_QUOTES) ?></td>
				<td><?= htmlspecialchars($item['username'], ENT_QUOTES) ?></td>
				<td><?= htmlspecialchars($item['operation'], ENT_QUOTES) ?></td>
				<td><?= htmlspecialchars($item['status'], ENT_QUOTES) ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ($errors): ?>
	<div class="msg-bad">
		<strong><?= _('Warnings and errors') ?></strong>
		<ul>
		<?php foreach ($errors as $error): ?>
			<li><?= htmlspecialchars($error, ENT_QUOTES) ?></li>
		<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<div class="form-buttons">
	<a class="btn-alt" href="zabbix.php?action=user.policy">
		<?= _('Return to policy preview') ?>
	</a>
</div>
