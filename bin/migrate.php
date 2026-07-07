<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Database\Db;
use App\Database\MigrationRunner;

require __DIR__ . '/../vendor/autoload.php';

// Headless CLI — bootstrapuje jen DI container kvůli Db, žádné HTTP.
$container = Bootstrap::boot()->createContainer();
$db = $container->getByType(Db::class);

$runner = new MigrationRunner($db, __DIR__ . '/../migrations');

try {
	$applied = $runner->run();
} catch (Throwable $e) {
	fwrite(STDERR, 'Migrace selhala: ' . $e->getMessage() . PHP_EOL);
	exit(1);
}

if ($applied === []) {
	echo 'Žádné nové migrace.' . PHP_EOL;
	exit(0);
}

foreach ($applied as $version) {
	echo "Aplikováno: {$version}" . PHP_EOL;
}

exit(0);
