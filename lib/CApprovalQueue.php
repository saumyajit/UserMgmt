<?php

namespace Modules\UserMgmt\Lib;

/**
 * Very small flat-file queue for "flag for approval" disable requests.
 *
 * This is deliberately simple (one JSON file, whole-file read/write) to match
 * the existing On-Call Scheduler module's data/ storage pattern rather than
 * pulling in a DB table. Fine for the expected volume (tens of pending
 * requests at a time). If usage grows, swap the read/write pair below for a
 * DB-backed table — every call site only depends on the four static methods.
 */
class CApprovalQueue {

	private static function path(): string {
		return __DIR__ . '/data/approval_queue.json';
	}

	private static function load(): array {
		$file = self::path();
		if (!is_file($file)) {
			return [];
		}
		$raw = file_get_contents($file);
		$data = json_decode($raw, true);
		return is_array($data) ? $data : [];
	}

	private static function save(array $data): void {
		$dir = dirname(self::path());
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}
		// Write-then-rename to avoid readers seeing a half-written file.
		$tmp = self::path() . '.tmp';
		file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT));
		rename($tmp, self::path());
	}

	public static function isPending(string $userid): bool {
		foreach (self::load() as $entry) {
			if ($entry['userid'] === $userid && $entry['status'] === 'pending') {
				return true;
			}
		}
		return false;
	}

	public static function getAll(): array {
		return self::load();
	}

	public static function add(string $userid, string $username, string $requested_by,
			string $request_no, string $comment): void {
		$data = self::load();
		$data[] = [
			'id' => uniqid('appr_', true),
			'userid' => $userid,
			'username' => $username,
			'requested_by' => $requested_by,
			'request_no' => $request_no,
			'comment' => $comment,
			'status' => 'pending',
			'created' => time(),
			'resolved' => null,
			'resolved_by' => null
		];
		self::save($data);
	}

	public static function resolve(string $entry_id, string $status, string $resolved_by): void {
		$data = self::load();
		foreach ($data as &$entry) {
			if ($entry['id'] === $entry_id) {
				$entry['status'] = $status; // 'approved' or 'rejected'
				$entry['resolved'] = time();
				$entry['resolved_by'] = $resolved_by;
			}
		}
		unset($entry);
		self::save($data);
	}
}
