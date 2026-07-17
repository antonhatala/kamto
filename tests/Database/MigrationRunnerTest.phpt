<?php

declare(strict_types=1);

use App\Database\MigrationRunner;
use App\Database\PdoSqliteDb;
use Tester\Assert;
use Tests\Helpers\TestDatabase;

require __DIR__ . '/../bootstrap.php';

$path = tempnam(sys_get_temp_dir(), 'kamto-test-');
$db = new PdoSqliteDb($path);
$runner = new MigrationRunner($db, TestDatabase::migrationsDir());

$applied = $runner->run();
Assert::same(
	['001_init', '002_payment_skipped', '003_service_sliding', '004_drop_service_sort_order', '005_drop_note_and_icon'],
	$applied,
);
Assert::count(5, $db->fetchAll('SELECT * FROM _migration'));
Assert::same(0, $db->fetchField('SELECT COUNT(*) FROM category'));
Assert::same(0, $db->fetchField('SELECT COUNT(*) FROM service'));
Assert::same(0, $db->fetchField('SELECT COUNT(*) FROM payment'));

$secondRun = $runner->run();
Assert::same([], $secondRun);
Assert::count(5, $db->fetchAll('SELECT * FROM _migration'));

$brokenDir = sys_get_temp_dir() . '/kamto-broken-migrations-' . uniqid();
mkdir($brokenDir);
file_put_contents(
	$brokenDir . '/001_broken.sql',
	"CREATE TABLE widget (id INTEGER PRIMARY KEY);\nCREATE TABLE widget (id INTEGER PRIMARY KEY);\n",
);

$brokenPath = tempnam(sys_get_temp_dir(), 'kamto-test-');
$brokenDb = new PdoSqliteDb($brokenPath);
$brokenRunner = new MigrationRunner($brokenDb, $brokenDir);

Assert::exception(
	static fn() => $brokenRunner->run(),
	Throwable::class,
);

Assert::same(
	0,
	$brokenDb->fetchField("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'widget'"),
);
Assert::count(0, $brokenDb->fetchAll('SELECT * FROM _migration'));

unlink($brokenDir . '/001_broken.sql');
rmdir($brokenDir);
