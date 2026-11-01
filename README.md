# Aether Search

Search for Statamic sites, part of the Aether ecosystem of UAMS Statamic add-ons.

This repository was split out of [`UAMS-Web/uams-statamic`](https://github.com/UAMS-Web/uams-statamic) and is not released yet. It currently holds the add-on's scaffold (the service provider, the test and quality tooling, and the documentation set); the search code itself is moved in under its own ticket. This README describes only what exists, and grows as the add-on does.

## Requirements

The add-on needs PHP 8.4 or later and Statamic 6.35 or later, matching `composer.json`. `tests/Unit/ReadmeTest.php` fails when this README drifts from those floors.

## Installation

```bash
composer require uams-web/aether-search
```

The add-on registers itself through Statamic's add-on discovery. Its settings live in `config/aether-search.php`; publish it to the host with:

```bash
php artisan vendor:publish --tag=aether-search-config
```

## Development and submission

Developing against a host application is covered in [the development guide](docs/DEVELOPMENT.md), and the Packagist and Marketplace steps in [the submission checklist](docs/SUBMISSION.md). Decisions are recorded in [`docs/DECISIONS.md`](docs/DECISIONS.md).

## Namespace

The add-on's namespace is `UAMSWeb\Aether\Search\`. Every add-on in the Aether ecosystem sits under `UAMSWeb\Aether\`, in the shape `UAMSWeb\Aether\<Product>`, or `UAMSWeb\Aether\<Family>\<Product>` where a family groups packages. The level stays `UAMSWeb\<Product>` only for an add-on outside that ecosystem, and the family level is optional there too. The `autoload.psr-4` key maps that whole prefix to `src/` ([PSR-4](https://www.php-fig.org/psr/psr-4/) allows a prefix of any depth), so the files under `src/` do not move when the prefix changes. A prefix has one owner or none, and two packages never map the same one. Neither `UAMSWeb\Aether\` nor a family level that no package owns is mapped by anyone: `UAMSWeb\Aether\Forms\Settings\` and `UAMSWeb\Aether\Forms\Multisite\` are two packages' keys, and no package maps `UAMSWeb\Aether\Forms\`. A package may instead own its prefix with a companion nested inside it: one package maps `UAMSWeb\Aether\People\` and another maps `UAMSWeb\Aether\People\ListingsManagement\`. Composer picks the longest matching prefix, so a nested prefix works only when the outer package has no directory under `src/` named after the inner one (here, no `src/ListingsManagement/` in the `UAMSWeb\Aether\People\` package).

## License

The add-on has no license yet. The choice waits on the general decision from UAMS legal in [`UAMS-Web/uams-statamic#261`](https://github.com/UAMS-Web/uams-statamic/issues/261).
