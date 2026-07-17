<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;

final class Bootstrap
{
	public static function boot(): Configurator
	{
		$appDir = dirname(__DIR__);

		$debugMode = in_array(getenv('APP_ENV'), ['development', 'local'], true);

		$configurator = new Configurator;
		$configurator->setDebugMode($debugMode);
		$configurator->enableTracy($appDir . '/log');

		$configurator->setTempDirectory($appDir . '/temp');

		$env = getenv();
		$env['DATABASE_DRIVER'] = ($env['DATABASE_DRIVER'] ?? '') !== '' ? $env['DATABASE_DRIVER'] : 'pdo-sqlite';
		$env += [
			'APP_PASSWORD_HASH' => '',
			'DATABASE_URL' => '',
			'DATABASE_TOKEN' => '',
		];
		$configurator->addDynamicParameters(['env' => $env]);

		$configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();

		$configurator->addConfig($appDir . '/config/config.neon');

		if ($debugMode) {
			$configurator->addConfig($appDir . '/config/config.dev.neon');
		}

		$localConfig = $appDir . '/config/config.local.neon';
		if (is_file($localConfig)) {
			$configurator->addConfig($localConfig);
		}

		return $configurator;
	}
}
