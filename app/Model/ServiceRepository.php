<?php

declare(strict_types=1);

namespace App\Model;

use App\Database\Db;

final class ServiceRepository
{
	public function __construct(
		private readonly Db $db,
	) {
	}

	/** @return array<string, mixed>|null */
	public function find(int $id): ?array
	{
		return $this->db->fetch('SELECT * FROM service WHERE id = ?', [$id]);
	}

	/** @return array<string, mixed>|null */
	public function findActive(int $id): ?array
	{
		return $this->db->fetch('SELECT * FROM service WHERE id = ? AND is_archived = 0', [$id]);
	}

	/** @return list<array<string, mixed>> */
	public function findAll(bool $includeArchived = false): array
	{
		if ($includeArchived) {
			return $this->db->fetchAll('SELECT * FROM service ORDER BY is_sliding, due_day, id');
		}

		return $this->db->fetchAll('SELECT * FROM service WHERE is_archived = 0 ORDER BY is_sliding, due_day, id');
	}

	/** @return list<array<string, mixed>> */
	public function findArchived(): array
	{
		return $this->db->fetchAll('SELECT * FROM service WHERE is_archived = 1 ORDER BY is_sliding, due_day, id');
	}

	/**
	 * @param array{
	 *     name: string,
	 *     amount: int,
	 *     period: string,
	 *     due_day: int,
	 *     due_month?: int|null,
	 *     category_id?: int|null,
	 *     is_sliding?: int,
	 * } $data
	 */
	public function insert(array $data): int
	{
		$this->db->execute(
			'INSERT INTO service
				(name, amount, period, due_day, due_month, category_id, created_at, is_sliding)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
			[
				$data['name'],
				$data['amount'],
				$data['period'],
				$data['due_day'],
				$data['due_month'] ?? null,
				$data['category_id'] ?? null,
				date(DATE_ATOM),
				$data['is_sliding'] ?? 0,
			],
		);

		return $this->db->lastInsertId();
	}

	/**
	 * @param array{
	 *     name: string,
	 *     amount: int,
	 *     period: string,
	 *     due_day: int,
	 *     due_month: int|null,
	 *     category_id: int|null,
	 *     is_sliding: int,
	 * } $data
	 */
	public function update(int $id, array $data): void
	{
		$this->db->execute(
			'UPDATE service SET
				name = ?, amount = ?, period = ?, due_day = ?, due_month = ?,
				category_id = ?, is_sliding = ?
				WHERE id = ?',
			[
				$data['name'],
				$data['amount'],
				$data['period'],
				$data['due_day'],
				$data['due_month'],
				$data['category_id'],
				$data['is_sliding'],
				$id,
			],
		);
	}

	public function archive(int $id): void
	{
		$this->db->execute(
			'UPDATE service SET is_archived = 1, archived_at = ? WHERE id = ?',
			[date(DATE_ATOM), $id],
		);
	}

	public function reactivate(int $id): void
	{
		$this->db->execute(
			'UPDATE service SET is_archived = 0, archived_at = NULL WHERE id = ?',
			[$id],
		);
	}

	public function delete(int $id): void
	{
		$this->db->execute('DELETE FROM service WHERE id = ?', [$id]);
	}
}
