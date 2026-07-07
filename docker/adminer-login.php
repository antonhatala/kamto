<?php

// Adminer (5.x) odmítá přihlášení úplně bez hesla a SQLite žádné heslo nemá. Tenhle
// soubor se mountuje do /var/www/html/plugins-enabled/ (viz docker-compose.yml) a aktivuje
// oficiální plugin login-password-less: do Admineru se vstupuje "vstupním" heslem `kamto`
// (stejné jako lokální heslo do appky — jen lokální vývoj, hash níže není tajemství).
require_once('plugins/login-password-less.php');

return new AdminerLoginPasswordLess(
	// password_hash('kamto', PASSWORD_DEFAULT)
	'$2y$12$hiVouMjbTynUn8yaOo79quGx2s2zUETxIHIF1lj5uujKLarWzK8SC',
);
