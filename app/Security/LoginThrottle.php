<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Jednoduchý globální throttle na neúspěšná přihlášení — single-user appka, žádná DB,
 * žádná externí závislost. Stav (počet neúspěchů + čas posledního pokusu) drží v jednom
 * souboru (viz config.neon, parametr %tempDir%/login-throttle.json).
 *
 * Po `MaxAttemptsBeforeDelay` neúspěších roste zpoždění exponenciálně (2, 4, 8, … s), max.
 * `MaxDelaySeconds` (5 min). Úspěšné přihlášení čítač vynuluje.
 */
final class LoginThrottle
{
	private const int MaxAttemptsBeforeDelay = 5;
	private const int BaseDelaySeconds = 2;
	private const int MaxDelaySeconds = 300;

	public function __construct(
		private readonly string $stateFile,
	) {
	}

	/** Kolik sekund ještě čekat, než lze zkusit další přihlášení (0 = lze zkusit hned). */
	public function secondsUntilRetry(?int $now = null): int
	{
		$now ??= time();
		$state = $this->load();

		if ($state['attempts'] < self::MaxAttemptsBeforeDelay) {
			return 0;
		}

		$elapsed = $now - $state['lastAttemptAt'];
		$remaining = $this->delayFor($state['attempts']) - $elapsed;

		return max(0, $remaining);
	}

	/** Zaznamená neúspěšný pokus (posouvá se čas posledního pokusu, roste zpoždění). */
	public function registerFailure(?int $now = null): void
	{
		$now ??= time();
		$this->mutate(static fn(array $state): array => [
			'attempts' => $state['attempts'] + 1,
			'lastAttemptAt' => $now,
		]);
	}

	/** Úspěšné přihlášení — vynuluje čítač. */
	public function registerSuccess(): void
	{
		$this->mutate(static fn(array $state): array => ['attempts' => 0, 'lastAttemptAt' => 0]);
	}

	private function delayFor(int $attempts): int
	{
		$stepsOverLimit = max(1, $attempts - self::MaxAttemptsBeforeDelay + 1);
		// Exponent omezený na 10 — 2^10 × base už dávno přesahuje strop, netřeba počítat dál
		// (a chrání to před přetečením do float při extrémně vysokém počtu pokusů).
		$exponent = min($stepsOverLimit - 1, 10);
		$delay = (int) (self::BaseDelaySeconds * 2 ** $exponent);

		return min($delay, self::MaxDelaySeconds);
	}

	/** @return array{attempts: int, lastAttemptAt: int} */
	private function load(): array
	{
		if (!is_file($this->stateFile)) {
			return self::defaultState();
		}

		$json = file_get_contents($this->stateFile);

		return $this->parse($json === false ? null : $json);
	}

	/**
	 * Atomicky přečte stav, aplikuje $modify a zapíše výsledek — celý read-modify-write pod
	 * jedním exkluzivním zámkem (flock), aby souběžný burst neúspěchů neztratil inkrementy.
	 *
	 * @param callable(array{attempts: int, lastAttemptAt: int}): array{attempts: int, lastAttemptAt: int} $modify
	 */
	private function mutate(callable $modify): void
	{
		$dir = dirname($this->stateFile);
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}

		// 'c+' otevře pro čtení i zápis a soubor vytvoří, když neexistuje (bez oříznutí).
		$handle = fopen($this->stateFile, 'c+');
		if ($handle === false) {
			return;
		}

		try {
			flock($handle, LOCK_EX);
			$json = stream_get_contents($handle);
			$newState = $modify($this->parse($json === false ? null : $json));

			rewind($handle);
			ftruncate($handle, 0);
			fwrite($handle, (string) json_encode($newState));
			fflush($handle);
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	/**
	 * @return array{attempts: int, lastAttemptAt: int}
	 */
	private function parse(?string $json): array
	{
		$data = $json === null || $json === '' ? null : json_decode($json, true);

		if (!is_array($data) || !isset($data['attempts'], $data['lastAttemptAt'])) {
			return self::defaultState();
		}

		return [
			'attempts' => (int) $data['attempts'],
			'lastAttemptAt' => (int) $data['lastAttemptAt'],
		];
	}

	/** @return array{attempts: int, lastAttemptAt: int} */
	private static function defaultState(): array
	{
		return ['attempts' => 0, 'lastAttemptAt' => 0];
	}
}
