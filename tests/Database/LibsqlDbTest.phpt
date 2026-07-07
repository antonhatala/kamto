<?php

declare(strict_types=1);

use App\Database\LibsqlDb;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// V tomto image (Fáze 1) není nahraná extension `libsql` (tursodatabase/turso-client-php) —
// konstruktor musí hlásit jasnou chybu místo fatálního "Class LibSQL not found". Až extension
// do image přibude (Fáze 6), tenhle test bude potřeba přepsat na reálné spojení.
Assert::exception(
	static fn() => new LibsqlDb('file:var/should-not-be-created.db'),
	RuntimeException::class,
);
