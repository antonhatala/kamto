<?php

declare(strict_types=1);

use App\Database\LibsqlDb;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::exception(
	static fn() => new LibsqlDb('file:var/should-not-be-created.db'),
	RuntimeException::class,
);
