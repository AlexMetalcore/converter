.. code-block:: bash
    Install composer require alexsmartalexandrov/converter

Usage
-----

Creating``ConverterService`` instance:

.. code-block:: php

    use \ConverterService\ConverterService;

    $service = new \ConverterService\ConverterService();
    
    1. $service->prepareDataFormat($requestObject, ConverterServicesInterface $converter)
    2. $service->setHttpClient($httpClient)->convertPdfToHtml('filepath, url or base64 pdf', $options[])
