<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;

final class Bootstrap
{
	public static function boot(): Configurator
	{
		$appDir = dirname(__DIR__);

		// Fail-closed: debug/Tracy zapnuté jen pro explicitní vývojové prostředí (allowlist).
		// Chybějící proměnná i překlep (cokoliv jiného než 'development'/'local') → produkční
		// režim. docker-compose lokálně nastavuje APP_ENV=development (viz docker-compose.yml).
		$debugMode = in_array(getenv('APP_ENV'), ['development', 'local'], true);

		$configurator = new Configurator;
		$configurator->setDebugMode($debugMode);
		$configurator->enableTracy($appDir . '/log');

		$configurator->setTempDirectory($appDir . '/temp');

		// Exposes environment variables as %env.NAME% in NEON configs. Volitelné klíče musí být
		// definované, aby se %env.X% vyhodnotilo i bez nastavené proměnné (jinak Nette hodí chybu
		// na chybějícím parametru).
		//   DATABASE_DRIVER — chybějící NEBO prázdný ('' z docker-compose `${VAR:-}`) → lokální
		//     pdo-sqlite; na Bunny 'libsql' (Fáze 6). Prázdný string musí spadnout na default,
		//     jinak by DbFactory dostal '' a shodil se.
		//   APP_PASSWORD_HASH — lokálně prázdný, přebíjí ho config.local.neon; na Bunny reálný hash.
		//   DATABASE_URL/TOKEN — prázdné je validní stav (pdo-sqlite je nepoužije).
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

		// Vývojové override (CSP off pro Tracy apod.) — jen v debug módu. Produkce ho z principu
		// míjí → CSP z config.neon reálně platí (fail-closed dle APP_ENV, ne dle přítomnosti souboru).
		if ($debugMode) {
			$configurator->addConfig($appDir . '/config/config.dev.neon');
		}

		// Lokální tajnosti (appPasswordHash) — gitignored + dockerignored, v produkčním image není.
		$localConfig = $appDir . '/config/config.local.neon';
		if (is_file($localConfig)) {
			$configurator->addConfig($localConfig);
		}

		return $configurator;
	}
}
