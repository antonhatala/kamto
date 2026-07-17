<?php

declare(strict_types=1);

namespace App\Security;

final class LoginThrottle
{
	private const int MaxAttemptsBeforeDelay = 5;
	private const int BaseDelaySeconds = 2;
	private const int MaxDelaySeconds = 300;

	public function __construct(
		private readonly string $stateFile,
	) {
	}

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

	public function registerFailure(?int $now = null): void
	{
		$now ??= time();
		$this->mutate(static fn(array $state): array => [
			'attempts' => $state['attempts'] + 1,
			'lastAttemptAt' => $now,
		]);
	}

	public function registerSuccess(): void
	{
		$this->mutate(static fn(array $state): array => ['attempts' => 0, 'lastAttemptAt' => 0]);
	}

	private function delayFor(int $attempts): int
	{
		$stepsOverLimit = max(1, $attempts - self::MaxAttemptsBeforeDelay + 1);
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
	 * @param callable(array{attempts: int, lastAttemptAt: int}): array{attempts: int, lastAttemptAt: int} $modify
	 */
	private function mutate(callable $modify): void
	{
		$dir = dirname($this->stateFile);
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}

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
