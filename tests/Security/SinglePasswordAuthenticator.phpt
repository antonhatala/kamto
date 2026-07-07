<?php

declare(strict_types=1);

use App\Security\SinglePasswordAuthenticator;
use Nette\Security\AuthenticationException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$hash = password_hash('kamto', PASSWORD_DEFAULT);
$authenticator = new SinglePasswordAuthenticator($hash);

// Correct password -> returns an identity, username is ignored.
$identity = $authenticator->authenticate('anything', 'kamto');
Assert::same(SinglePasswordAuthenticator::UserId, $identity->getId());

// Wrong password -> throws.
Assert::exception(
	static fn() => $authenticator->authenticate('', 'wrong-password'),
	AuthenticationException::class,
);

// Empty hash never matches (defensive default, no accidental open login).
$lockedOut = new SinglePasswordAuthenticator('');
Assert::exception(
	static fn() => $lockedOut->authenticate('', ''),
	AuthenticationException::class,
);
