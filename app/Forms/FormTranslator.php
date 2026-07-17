<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Localization\Translator;
use Stringable;

final class FormTranslator implements Translator
{
	private const array Messages = [
		'This field is required.' => 'Toto pole je povinné.',
		'This field should be blank.' => 'Toto pole musí být prázdné.',
		'Please enter %s.' => 'Zadejte %s.',
		'This value should not be %s.' => 'Tato hodnota nesmí být %s.',
		'Please enter at least %d characters.' => 'Zadejte alespoň %d znaků.',
		'Please enter no more than %d characters.' => 'Zadejte nejvýše %d znaků.',
		'Please enter a value between %d and %d characters long.' => 'Zadejte hodnotu dlouhou %d až %d znaků.',
		'Please enter a valid email address.' => 'Zadejte platnou e-mailovou adresu.',
		'Please enter a valid URL.' => 'Zadejte platnou URL adresu.',
		'Please enter a valid integer.' => 'Zadejte celé číslo.',
		'Please enter a valid number.' => 'Zadejte platné číslo.',
		'Please enter a value greater than or equal to %d.' => 'Zadejte hodnotu větší nebo rovnou %d.',
		'Please enter a value less than or equal to %d.' => 'Zadejte hodnotu menší nebo rovnou %d.',
		'Please enter a value between %d and %d.' => 'Zadejte hodnotu mezi %d a %d.',
		'Please select a valid option.' => 'Vyberte platnou možnost.',
		'The size of the uploaded file can be up to %d bytes.' => 'Nahraný soubor může mít nejvýše %d bajtů.',
		'The uploaded data exceeds the limit of %d bytes.' => 'Nahraná data překračují limit %d bajtů.',
		'The uploaded file is not in the expected format.' => 'Nahraný soubor nemá očekávaný formát.',
		'The uploaded file must be image in format JPEG, GIF, PNG or WebP.' => 'Nahraný soubor musí být obrázek ve formátu JPEG, GIF, PNG nebo WebP.',
		'An error occurred during file upload.' => 'Při nahrávání souboru došlo k chybě.',
		'Your session has expired. Please return to the home page and try again.' => 'Vypršel časový limit, vraťte se na úvodní stránku a zkuste to znovu.',
	];

	public function translate(string|Stringable $message, mixed ...$parameters): string|Stringable
	{
		return self::Messages[(string) $message] ?? $message;
	}
}
