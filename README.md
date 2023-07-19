# CmsSystem

[![Latest Version on Packagist](https://img.shields.io/packagist/v/portavice/cmssytem.svg?style=flat-square)](https://packagist.org/packages/portavice/cmssytem)
![Test Status](https://img.shields.io/github/actions/workflow/status/portavice/CmsSystem/tests.yml?branch=main&label=Tests)
![Code Style Status](https://img.shields.io/github/actions/workflow/status/portavice/CmsSystem/code-style.yml?branch=main&label=Code%20Style)
<a href="https://packagist.org/packages/portavice/cmssytem"><img src="https://img.shields.io/packagist/php-v/portavice/cmssytem.svg?style=flat-square" alt="PHP from Packagist"></a>
[![Total Downloads](https://img.shields.io/packagist/dt/portavice/cmssytem.svg?style=flat-square)](https://packagist.org/packages/portavice/cmssytem)

## Installation
Um die Projekt **CmsSystem** in Ihrem Projekt zu verwenden, fügen Sie sie einfach in Ihr Projektverzeichnis hinzu oder installieren Sie sie über Composer:

```bash
composer require portavice/cmssystem
```

Füge Sie anschließend die Config-Datei ein:
```bash
php artisan vendor:publish --provider="Portavice\CmsSystem\CmsSystemServiceProvider"
```

## Updaten
Um das Projekt **CmsSystem** zu aktualisieren, führen Sie einfach den folgenden Befehl aus:

```bash
composer update portavice/cmssystem
```

Anschließend können Sie die Config-Datei aktualisieren:
```bash
php artisan vendor:publish --provider="Portavice\CmsSystem\CmsSystemServiceProvider" --tag="config" --force
```

## Verwendung
Um die CmsSystem-Klasse in Ihrem Projekt zu verwenden, müssen Sie sie zuerst importieren und eine Instanz der Klasse erstellen:

```php
use Portavice\CmsSystem\CmsSystem;

$cms = new CmsSystem();
```

## Methoden
Die Klasse CmsSystem bietet verschiedene Methoden zum Ersetzen von Platzhaltern und zur Manipulation von Inhalten. Hier sind die wichtigsten Methoden:

### setContent
```php
public function setContent(string $content): self
```
Setzt den Inhalt, auf dem die Platzhalter ersetzt werden sollen.

### setParams
```php
public function setParams(array $params): self
```
Setzt eine Liste von Parametern, die in den Platzhaltern verwendet werden können. Die Parameter werden als assoziatives Array mit Schlüssel-Wert-Paaren übergeben.

### setParam
```php
public function setParam(string $key, mixed $value): self
```
Setzt einen einzelnen Parameter mit dem angegebenen Schlüssel und Wert.

### removeParam
```php
public function removeParam(string $key): self
```
Entfernt den Parameter mit dem angegebenen Schlüssel.

### replace
```php
public function replace(?string $content = null): string
```
Ersetzt die Platzhalter im angegebenen Inhalt (oder im zuvor gesetzten Inhalt) und gibt den resultierenden Text zurück.

### splitPattern
```php
public function splitPattern(string $content): array
```
Teilt den angegebenen Inhalt in Blöcke anhand der definierten Muster und gibt ein Array von Blöcken zurück.

Hinweis: Die weiteren Methoden sind intern und werden von der Klasse verwendet, um die Platzhalter zu manipulieren. Sie können diese Methoden verwenden, wenn Sie erweiterte Anpassungen vornehmen möchten.

## Beispiel
```php
use Portavice\CmsSystem\CmsSystem;

$cms = new CmsSystem();

$content = "
    {{ var some_variable }}
    {{ if some_condition }}
        This content is shown if 'some_condition' is true.
    {{ else }}
        This content is shown if 'some_condition' is false.
    {{ endif }}
    {{ foreach items as item }}
        {{ item.name }}
    {{ endforeach }}
";

$params = [
    'some_variable' => 'Hello, World!',
    'some_condition' => true,
    'items' => [
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
        ['name' => 'Item 3'],
],
];

echo $cms->setParams($params)->replace($content);
```
Dieses Beispiel demonstriert die Verwendung der CmsSystem-Klasse, um Platzhalter im $content zu ersetzen. Der resultierende Text wird anschließend ausgegeben.

## Hinweis
Bitte beachten Sie, dass diese README.md nur einen grundlegenden Überblick über die CmsSystem-Klasse bietet. Für detaillierte Informationen und weitere Anpassungen empfehle ich Ihnen, den Quellcode der Klasse zu überprüfen und die verfügbaren Methoden zu erkunden.

## Autor
Die Klasse CmsSystem wurde von Portavice entwickelt und steht unter der MIT-Lizenz. Weitere Informationen finden Sie in der LICENSE-Datei.

## Fehler melden
Wenn Sie Fehler oder Verbesserungsvorschläge finden, melden Sie diese bitte als Issue in diesem Repository.

Viel Spaß beim Verwenden von CmsSystem!

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Development

### How to develop
- Run `composer install` to install the dependencies for PHP.
- Run `composer cs` to check compliance with the code style and `composer csfix` to fix code style violations before every commit.

### Code Style
PHP code MUST follow [PSR-12 specification](https://www.php-fig.org/psr/psr-12/).

We use [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) for the PHP code style check.
