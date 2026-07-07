<?php

declare(strict_types=1);

namespace App\Model;

use App\Database\Db;

/**
 * Raw SQL repozitář nad tabulkou `service` (opakující se šablona platby) — žádná business
 * logika (výpočet splatnosti je Fáze 3).
 */
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

	/** @return list<array<string, mixed>> */
	public function findAll(bool $includeArchived = false): array
	{
		if ($includeArchived) {
			return $this->db->fetchAll('SELECT * FROM service ORDER BY sort_order, id');
		}

		return $this->db->fetchAll('SELECT * FROM service WHERE is_archived = 0 ORDER BY sort_order, id');
	}

	/**
	 * @param array{
	 *     name: string,
	 *     amount: int,
	 *     period: string,
	 *     due_day: int,
	 *     due_month?: int|null,
	 *     category_id?: int|null,
	 *     icon?: string|null,
	 *     note?: string|null,
	 *     created_at: string,
	 *     sort_order?: int,
	 * } $data
	 */
	public function insert(array $data): int
	{
		$this->db->execute(
			'INSERT INTO service
				(name, amount, period, due_day, due_month, category_id, icon, note, created_at, sort_order)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[
				$data['name'],
				$data['amount'],
				$data['period'],
				$data['due_day'],
				$data['due_month'] ?? null,
				$data['category_id'] ?? null,
				$data['icon'] ?? null,
				$data['note'] ?? null,
				$data['created_at'],
				$data['sort_order'] ?? 0,
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
	 *     icon: string|null,
	 *     note: string|null,
	 *     sort_order: int,
	 * } $data
	 */
	public function update(int $id, array $data): void
	{
		$this->db->execute(
			'UPDATE service SET
				name = ?, amount = ?, period = ?, due_day = ?, due_month = ?,
				category_id = ?, icon = ?, note = ?, sort_order = ?
				WHERE id = ?',
			[
				$data['name'],
				$data['amount'],
				$data['period'],
				$data['due_day'],
				$data['due_month'],
				$data['category_id'],
				$data['icon'],
				$data['note'],
				$data['sort_order'],
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
