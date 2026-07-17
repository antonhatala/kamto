<?php

declare(strict_types=1);

namespace App\Security;

use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\SimpleIdentity;

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
