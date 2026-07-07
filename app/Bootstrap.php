<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;

final class Bootstrap
{
	public static function boot(): Configurator
	{
		$appDir = dirname(__DIR__);
		$debugMode = getenv('APP_ENV') !== 'production';

		$configurator = new Configurator;
		$configurator->setDebugMode($debugMode);
		$configurator->enableTracy($appDir . '/log');

		$configurator->setTempDirectory($appDir . '/temp');

		// Exposes environment variables as %env.NAME% in NEON configs (e.g. APP_PASSWORD_HASH on production).
		$configurator->addDynamicParameters(['env' => getenv()]);

		$configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();

		$configurator->addConfig($appDir . '/config/config.neon');

		$localConfig = $appDir . '/config/config.local.neon';
		if (is_file($localConfig)) {
			$configurator->addConfig($localConfig);
		}

		return $configurator;
	}
}
