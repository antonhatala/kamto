<?php

declare(strict_types=1);

namespace App\Security;

use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\SimpleIdentity;

/**
 * Single-user authentication — no username, no user table.
 * The account's identity is always 'user'; only the password is verified
 * against a bcrypt/argon hash supplied via config (appPasswordHash).
 */
final class SinglePasswordAuthenticator implements Authenticator
{
	public const UserId = 'user';

	public function __construct(
		private readonly string $passwordHash,
	) {
	}

	public function authenticate(string $username, string $password): SimpleIdentity
	{
		if ($this->passwordHash === '' || !password_verify($password, $this->passwordHash)) {
			throw new AuthenticationException('Nesprávné heslo.', self::InvalidCredential);
		}

		return new SimpleIdentity(self::UserId);
	}
}
