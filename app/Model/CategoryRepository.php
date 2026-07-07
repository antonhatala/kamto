<?php

declare(strict_types=1);

namespace App\Model;

use App\Database\Db;

/** Raw SQL repozitář nad tabulkou `category` — žádná business logika. */
final class CategoryRepository
{
	public function __construct(
		private readonly Db $db,
	) {
	}

	/** @return list<array<string, mixed>> */
	public function findAll(): array
	{
		return $this->db->fetchAll('SELECT * FROM category ORDER BY sort_order, id');
	}

	/** @return array<string, mixed>|null */
	public function find(int $id): ?array
	{
		return $this->db->fetch('SELECT * FROM category WHERE id = ?', [$id]);
	}

	/** @param array{name: string, color: string, sort_order?: int} $data */
	public function insert(array $data): int
	{
		$this->db->execute(
			'INSERT INTO category (name, color, sort_order) VALUES (?, ?, ?)',
			[$data['name'], $data['color'], $data['sort_order'] ?? 0],
		);

		return $this->db->lastInsertId();
	}

	/** @param array{name: string, color: string, sort_order: int} $data */
	public function update(int $id, array $data): void
	{
		$this->db->execute(
			'UPDATE category SET name = ?, color = ?, sort_order = ? WHERE id = ?',
			[$data['name'], $data['color'], $data['sort_order'], $id],
		);
	}

	public function delete(int $id): void
	{
		$this->db->execute('DELETE FROM category WHERE id = ?', [$id]);
	}
}
