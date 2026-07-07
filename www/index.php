<?php

declare(strict_types=1);

use App\Bootstrap;
use Nette\Application\Application;

// When running via PHP's built-in server (`php -S ... www/index.php`), let it serve
// existing static files (css/, js/, manifest.json, ...) directly instead of routing
// them through the Nette application. No-op under nginx/Apache with a real docroot.
if (PHP_SAPI === 'cli-server') {
	$file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	if (is_file($file)) {
		return false;
	}
}

require __DIR__ . '/../vendor/autoload.php';

$container = Bootstrap::boot()->createContainer();
$container->getByType(Application::class)->run();
