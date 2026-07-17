<?php

declare(strict_types=1);

use App\Forms\FormTranslator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$translator = new FormTranslator;

Assert::same('Zadejte celé číslo.', $translator->translate('Please enter a valid integer.'));
Assert::same('Vyberte platnou možnost.', $translator->translate('Please select a valid option.'));
Assert::same('Toto pole je povinné.', $translator->translate('This field is required.'));

Assert::same('Zadejte nejvýše %d znaků.', $translator->translate('Please enter no more than %d characters.'));

Assert::same('Zadejte platnou částku.', $translator->translate('Zadejte platnou částku.'));
Assert::same('Den splatnosti musí být 1–31.', $translator->translate('Den splatnosti musí být 1–31.'));
