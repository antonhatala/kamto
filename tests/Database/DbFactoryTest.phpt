<?php

declare(strict_types=1);

use App\Database\DbFactory;
use App\Database\PdoSqliteDb;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$path = tempnam(sys_get_temp_dir(), 'kamto-test-');
Assert::type(
	PdoSqliteDb::class,
	DbFactory::create(['driver' => 'pdo-sqlite', 'path' => $path, 'url' => '', 'token' => '']),
);

Assert::exception(
	static fn() => DbFactory::create(['driver' => 'unknown', 'path' => '', 'url' => '', 'token' => '']),
	InvalidArgumentException::class,
);
