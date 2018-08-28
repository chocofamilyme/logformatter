# Phalcon - для форматирование логов

Для создания логов с параметрами для отслеживания запроса

## Требуется
    - Phalcon > 3.0.0
    - chocofamilyme/pathcorrelation 
    
## Использование

В конфигурационный файл нужно указать параметр domain

````php
return [
    'domain' => env('APP_DOMAIN', 'api.domain.me'),
];
````
