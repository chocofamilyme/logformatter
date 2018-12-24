# Phalcon - для форматирование логов

Для создания логов с параметрами для отслеживания запроса

## Требуется
    - Phalcon > 3.0.0
    - chocofamilyme/pathcorrelation
    - sentry/sentry
    
## Использование

В конфигурационный файл нужно указать параметр domain

````php
return [
    'domain' => env('APP_DOMAIN', 'api.domain.me'),
];
````

## Sentry
Ошибки можно отправлять в Sentry.

Файл с настройками:
````php
    'credential'   => [
        'key'       => env('SENTRY_KEY'),
        'projectId' => env('SENTRY_PROJECT_ID'),
        'domain'    => env('SENTRY_DOMAIN'),
    ],
    'options'      => [
        'curl_method' => 'async',
        'prefixes'    => [],
        'app_path'    => '',
        'timeout'     => 2,
    ],
    'environments' => ['production', 'staging'],
    'levels'       => [\Phalcon\Logger::EMERGENCY, \Phalcon\Logger::CRITICAL, \Phalcon\Logger::ERROR],
    'dontReport'   => [],
````

Пример:
````php
$di->setShared('sentry', function () use ($config) {
    return new \Chocofamily\Logger\Adapter\Sentry($config, 'production');
});


 $di->getShared('sentry')->logException($e, [], \Phalcon\Logger::ERROR);
````
