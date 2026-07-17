<?php

declare(strict_types=1);

use App\Security\SinglePasswordAuthenticator;
use Nette\Security\AuthenticationException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$hash = password_hash('kamto', PASSWORD_DEFAULT);
$authenticator = new SinglePasswordAuthenticator($hash);

$identity = $authenticator->authenticate('anything', 'kamto');
Assert::same(SinglePasswordAuthenticator::UserId, $identity->getId());

Assert::exception(
	static fn() => $authenticator->authenticate('', 'wrong-password'),
	AuthenticationException::class,
);

$lockedOut = new SinglePasswordAuthenticator('');
Assert::exception(
	static fn() => $lockedOut->authenticate('', ''),
	AuthenticationException::class,
);
