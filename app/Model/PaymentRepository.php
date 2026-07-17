<?php

declare(strict_types=1);

namespace App\Model;

use App\Database\Db;

final class PaymentRepository
{
	public function __construct(
		private readonly Db $db,
	) {
	}

	/** @return array<string, mixed>|null */
	public function find(int $id): ?array
	{
		return $this->db->fetch('SELECT * FROM payment WHERE id = ?', [$id]);
	}

	/** @return array<string, mixed>|null */
	public function findByServiceAndPeriod(int $serviceId, int $year, int $month): ?array
	{
		return $this->db->fetch(
			'SELECT * FROM payment WHERE service_id = ? AND period_year = ? AND period_month = ?',
			[$serviceId, $year, $month],
		);
	}

	/** @return list<array<string, mixed>> */
	public function findByService(int $serviceId): array
	{
		return $this->db->fetchAll(
			'SELECT * FROM payment WHERE service_id = ? ORDER BY period_year, period_month',
			[$serviceId],
		);
	}

	/** @return list<array<string, mixed>> */
	public function findByPeriod(int $year, int $month): array
	{
		return $this->db->fetchAll(
			'SELECT * FROM payment WHERE period_year = ? AND period_month = ? ORDER BY service_id',
			[$year, $month],
		);
	}

	/** @return list<array<string, mixed>> */
	public function findByYear(int $year): array
	{
		return $this->db->fetchAll(
			'SELECT * FROM payment WHERE period_year = ? ORDER BY period_month, service_id',
			[$year],
		);
	}

	/**
	 * @param array{
	 *     service_id: int,
	 *     period_year: int,
	 *     period_month: int,
	 *     due_date: string,
	 *     paid_date?: string|null,
	 *     skipped_at?: string|null,
	 *     amount: int,
	 * } $data
	 */
	public function insert(array $data): int
	{
		$this->db->execute(
			'INSERT INTO payment
				(service_id, period_year, period_month, due_date, paid_date, skipped_at, amount, created_at)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
			[
				$data['service_id'],
				$data['period_year'],
				$data['period_month'],
				$data['due_date'],
				$data['paid_date'] ?? null,
				$data['skipped_at'] ?? null,
				$data['amount'],
				date(DATE_ATOM),
			],
		);

		return $this->db->lastInsertId();
	}

	/**
	 * @param array{
	 *     service_id: int,
	 *     period_year: int,
	 *     period_month: int,
	 *     due_date: string,
	 *     paid_date?: string|null,
	 *     skipped_at?: string|null,
	 *     amount: int,
	 * } $data
	 */
	public function insertIgnore(array $data): void
	{
		$this->db->execute(
			'INSERT INTO payment
				(service_id, period_year, period_month, due_date, paid_date, skipped_at, amount, created_at)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?)
				ON CONFLICT (service_id, period_year, period_month) DO NOTHING',
			[
				$data['service_id'],
				$data['period_year'],
				$data['period_month'],
				$data['due_date'],
				$data['paid_date'] ?? null,
				$data['skipped_at'] ?? null,
				$data['amount'],
				date(DATE_ATOM),
			],
		);
	}

	/**
	 * @param array{
	 *     due_date: string,
	 *     paid_date: string|null,
	 *     amount: int,
	 * } $data
	 */
	public function update(int $id, array $data): void
	{
		$this->db->execute(
			'UPDATE payment SET due_date = ?, paid_date = ?, amount = ? WHERE id = ?',
			[$data['due_date'], $data['paid_date'], $data['amount'], $id],
		);
	}

	public function delete(int $id): void
	{
		$this->db->execute('DELETE FROM payment WHERE id = ?', [$id]);
	}

	public function setPaidDate(int $id, ?string $paidDate): void
	{
		$this->db->execute('UPDATE payment SET paid_date = ? WHERE id = ?', [$paidDate, $id]);
	}

	public function setSkipped(int $id, ?string $skippedAt): void
	{
		$this->db->execute('UPDATE payment SET skipped_at = ? WHERE id = ?', [$skippedAt, $id]);
	}

	public function setAmount(int $id, int $amount): void
	{
		$this->db->execute('UPDATE payment SET amount = ? WHERE id = ?', [$amount, $id]);
	}
}
