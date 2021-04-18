Установка
------------

```bash
    Install composer require alexsmartalexandrov/converter
```

Использование
-----

Создайте объект ``ConverterService``:

``` php

    use \ConverterService\ConverterService;

    $service = new \ConverterService\ConverterService();
```
Если вы хотите получить преобразование с реквеста данные в формат с HTML в CSV или наборот Вам нужно реализовать ConverterServicesInterface 
``` php 
    $service->getDataFormat($requestObject, ConverterServicesInterface $converter) 
```

Если вы хотите конвертировать PDF в html, Вам нужно передать либо строку либо url, либо закодированный формат pdf. Опции для оптимизации html строки на выходе.

Пример опций

```
    Формат опций массив вида
     ['image' => 'png', 'style' => [
              'p' => 'position:absolute; top:70px; left:65px; white-space:nowrap',
          ]
     ]
```
``` php
    $service->convertPdfToHtml('filepath, url or base64 pdf', $options[])
```
По умолчанию данные по урл получаем через file_get_contents. Если вы хотите использовать http client то можете его передать таким образом

``` php
    $service->setHttpClient($httpClient)->convertPdfToHtml('filepath, url or base64 pdf', $options[])
```
