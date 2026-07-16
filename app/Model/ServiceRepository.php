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

	/**
	 * Aktivní (nearchivovaná) služba, nebo null. Platební akce se smí týkat jen aktivní
	 * služby — archivovaná do dashboardu nevstupuje a nesmí přijmout platební signál
	 * (crafted POST musí skončit 404, viz HomePresenter::assertActiveService a PaymentService::upsert).
	 *
	 * @return array<string, mixed>|null
	 */
	public function findActive(int $id): ?array
	{
		return $this->db->fetch('SELECT * FROM service WHERE id = ? AND is_archived = 0', [$id]);
	}

	/** @return list<array<string, mixed>> */
	public function findAll(bool $includeArchived = false): array
	{
		if ($includeArchived) {
			return $this->db->fetchAll('SELECT * FROM service ORDER BY sort_order, id');
		}

		return $this->db->fetchAll('SELECT * FROM service WHERE is_archived = 0 ORDER BY sort_order, id');
	}

	/** @return list<array<string, mixed>> */
	public function findArchived(): array
	{
		return $this->db->fetchAll('SELECT * FROM service WHERE is_archived = 1 ORDER BY sort_order, id');
	}

	/** Pořadí pro nový záznam = o jedno za nejvyšším (napříč všemi vč. archivu). */
	public function nextSortOrder(): int
	{
		return (int) $this->db->fetchField('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM service');
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
	 *     sort_order?: int,
	 *     is_sliding?: int,
	 * } $data
	 */
	public function insert(array $data): int
	{
		$this->db->execute(
			'INSERT INTO service
				(name, amount, period, due_day, due_month, category_id, icon, note, created_at, sort_order, is_sliding)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[
				$data['name'],
				$data['amount'],
				$data['period'],
				$data['due_day'],
				$data['due_month'] ?? null,
				$data['category_id'] ?? null,
				$data['icon'] ?? null,
				$data['note'] ?? null,
				// created_at si repozitář generuje sám — volající ho neposílá (sjednoceno napříč repozitáři).
				date(DATE_ATOM),
				$data['sort_order'] ?? 0,
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
	 *     icon: string|null,
	 *     note: string|null,
	 *     sort_order: int,
	 *     is_sliding?: int,
	 * } $data
	 */
	public function update(int $id, array $data): void
	{
		$this->db->execute(
			'UPDATE service SET
				name = ?, amount = ?, period = ?, due_day = ?, due_month = ?,
				category_id = ?, icon = ?, note = ?, sort_order = ?, is_sliding = ?
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
				$data['is_sliding'] ?? 0,
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

	/**
	 * Prohodí sort_order dvou služeb v jedné transakci (řazení se sousedem, viz
	 * ServicePresenter::handleMoveUp/handleMoveDown). Atomické — buď se přehodí obě, nebo nic.
	 */
	public function swapSortOrder(int $idA, int $idB): void
	{
		$this->db->transaction(function () use ($idA, $idB): void {
			$orderA = (int) $this->db->fetchField('SELECT sort_order FROM service WHERE id = ?', [$idA]);
			$orderB = (int) $this->db->fetchField('SELECT sort_order FROM service WHERE id = ?', [$idB]);
			$this->setSortOrder($idA, $orderB);
			$this->setSortOrder($idB, $orderA);
		});
	}

	private function setSortOrder(int $id, int $sortOrder): void
	{
		$this->db->execute('UPDATE service SET sort_order = ? WHERE id = ?', [$sortOrder, $id]);
	}

	public function delete(int $id): void
	{
		$this->db->execute('DELETE FROM service WHERE id = ?', [$id]);
	}
}
