# BaksDev Ozon Manufacture

[![Version](https://img.shields.io/badge/version-7.2.1-blue)](https://github.com/baks-dev/ozon-manufacture/releases)
![php 8.4+](https://img.shields.io/badge/php-min%208.4-red.svg)
[![packagist](https://img.shields.io/badge/packagist-green)](https://packagist.org/packages/baks-dev/ozon-manufacture)

Модуль производства продукции заказов Ozon

## Установка

``` bash
composer require baks-dev/ozon-package
composer require baks-dev/ozon-manufacture
```

## Дополнительно

Установка конфигурации и файловых ресурсов:

``` bash
php bin/console baks:assets:install
```

Изменения в схеме базы данных с помощью миграции

``` bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Тестирование

``` bash
php bin/phpunit --group=ozon-manufacture
```

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.

