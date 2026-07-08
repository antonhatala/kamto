<?php

declare(strict_types=1);

use App\Forms\FormTranslator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$translator = new FormTranslator;

// Vestavěné anglické hlášky Nette Forms → čeština (regrese na QA nález: due_day s neceločíselným
// vstupem hlásil „Please enter a valid integer.", select mimo nabídku „Please select a valid option.").
Assert::same('Zadejte celé číslo.', $translator->translate('Please enter a valid integer.'));
Assert::same('Vyberte platnou možnost.', $translator->translate('Please select a valid option.'));
Assert::same('Toto pole je povinné.', $translator->translate('This field is required.'));

// Placeholder %d se v překladu zachová (dosazuje se až po překladu).
Assert::same('Zadejte nejvýše %d znaků.', $translator->translate('Please enter no more than %d characters.'));

// Neznámá (typicky už česká, vlastní) hláška projde beze změny.
Assert::same('Zadejte platnou částku.', $translator->translate('Zadejte platnou částku.'));
Assert::same('Den splatnosti musí být 1–31.', $translator->translate('Den splatnosti musí být 1–31.'));
