<?php

declare(strict_types=1);

use Tester\Assert;
use Tests\Helpers\TestDatabase;

require __DIR__ . '/../bootstrap.php';

$db = TestDatabase::create();

$affected = $db->execute('INSERT INTO category (name, color, sort_order) VALUES (?, ?, ?)', ['Bydlení', '#c1622e', 1]);
Assert::same(1, $affected);
$firstId = $db->lastInsertId();
Assert::same(1, $firstId);

$db->execute('INSERT INTO category (name, color, sort_order) VALUES (?, ?, ?)', ['Zábava', '#eac29c', 2]);

$all = $db->fetchAll('SELECT * FROM category ORDER BY sort_order');
Assert::count(2, $all);
Assert::same('Bydlení', $all[0]['name']);

$one = $db->fetch('SELECT * FROM category WHERE id = ?', [$firstId]);
Assert::same('Bydlení', $one['name']);

Assert::null($db->fetch('SELECT * FROM category WHERE id = ?', [999]));

$name = $db->fetchField('SELECT name FROM category WHERE id = ?', [$firstId]);
Assert::same('Bydlení', $name);
Assert::null($db->fetchField('SELECT name FROM category WHERE id = ?', [999]));

$db->executeScript('
	INSERT INTO category (name, color) VALUES (\'A\', \'#000\');
	INSERT INTO category (name, color) VALUES (\'B\', \'#000\');
');
Assert::same(4, $db->fetchField('SELECT COUNT(*) FROM category'));

$db->transaction(static function () use ($db): void {
	$db->execute('INSERT INTO category (name, color) VALUES (?, ?)', ['Commit test', '#000']);
});
Assert::same(1, $db->fetchField('SELECT COUNT(*) FROM category WHERE name = ?', ['Commit test']));

Assert::exception(
	static function () use ($db): void {
		$db->transaction(static function () use ($db): void {
			$db->execute('INSERT INTO category (name, color) VALUES (?, ?)', ['Rollback test', '#000']);
			throw new RuntimeException('boom');
		});
	},
	RuntimeException::class,
	'boom',
);
Assert::same(0, $db->fetchField('SELECT COUNT(*) FROM category WHERE name = ?', ['Rollback test']));

$result = $db->transaction(static fn(): string => 'ok');
Assert::same('ok', $result);

$tricky = ["O'Brien", "'); DROP TABLE service;--"];
foreach ($tricky as $value) {
	$db->execute('INSERT INTO category (name, color) VALUES (?, ?)', [$value, '#000']);
	Assert::same($value, $db->fetchField('SELECT name FROM category WHERE name = ?', [$value]));
}
Assert::same(0, $db->fetchField('SELECT COUNT(*) FROM service'));
