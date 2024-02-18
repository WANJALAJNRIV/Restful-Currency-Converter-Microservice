<?php

function generateErrorResponse($errorCode, $errorMessage)
{
    return [
        'error' => [
            'code' => $errorCode,
            'message' => $errorMessage,
        ],
    ];
}

function areRatesValid($timestamp)
{
    $currentTimestamp = time();
    $twoHoursInSeconds = 2 * 60 * 60;

    return ($currentTimestamp > $timestamp) && (($currentTimestamp - $timestamp) > $twoHoursInSeconds);
}

function currencyAlreadyUpToDateResponse()
{
    $doc = new DOMDocument('1.0', 'utf-8');
    $infoElement = $doc->createElement('info');
    $infoElement->nodeValue = 'Currency rate is already up-to-date.';
    $doc->appendChild($infoElement);

    return '<new >It is up to date</new>';
}

function fetchExchangeRates($baseCurrency, $type, $newCurrency = null,)
{
    if ($type == 'conversion') {
        // Assume your API or data source is queried here to get the rate for a specific currency
        // Replace this with your actual logic to fetch the rate for $newCurrency
        $rateForNewCurrency = 0.85; // Replace with your actual rate for the new currency

        $dummyData = [
            'success' => true,
            'timestamp' => time(),
            'base' => $baseCurrency,
            'date' => date('Y-m-d'),
            'rates' => [
                $newCurrency => $rateForNewCurrency,
            ],
        ];
    } else {
        $dummyData = [
            'success' => true,
            'timestamp' => time(),
            'base' => $baseCurrency,
            'date' => date('Y-m-d'),
            'rates' => [
                'EUR' => 0.85,
                'GBP' => 0.73,
                'JPY' => 109.23,
                'AUD' => 1.36,
            ],
        ];
    }

    return ['dummyData' => $dummyData];
}


function findExchangeRate($xml, $from, $to)
{
    $rateAmount = null;

    foreach ($xml->ExchangeRates as $exchangeRates) {
        if ($exchangeRates->BaseCurrency == $from) {
            foreach ($exchangeRates->Rate as $rate) {
                if ($rate->Currency == $to) {
                    $rateAmount = $rate->Value;
                }
            }
        }
    }

    return $rateAmount;
}

function updateRatesInXml($xml, $newRates, $baseCurrency)
{
    $filename = 'exchange_rates.xml';

    // Check if the XML file is empty
    if (empty($xml->ExchangeRates)) {
        // If empty, create a new base Rates element
        $ratesElement = $xml->addChild('Rates');
        echo "created rates";
        $xml->asXML($filename);
    }

    if (is_array($newRates) && count($newRates) > 1) {
        $existingBaseRates = $xml->xpath("//ExchangeRates[BaseCurrency = '$baseCurrency']");

        if (empty($existingBaseRates)) {
            // If the base currency doesn't exist, add it       
            $baseRatesElement = $xml->addChild('ExchangeRates');
            $baseRatesElement->addChild('BaseCurrency', $baseCurrency);
        }

        // Multiple rates case
        foreach ($newRates as $currency => $rate) {

            // Check if the currency already exists in the XML
            $existingRate = $xml->xpath("//ExchangeRates[BaseCurrency = '$baseCurrency']/Rate[Currency = '$currency']");

            if (empty($existingRate)) {
                // If the currency doesn't exist, add it
                $rateElement = $xml->xpath("//ExchangeRates[BaseCurrency = '$baseCurrency']")[0]->addChild('Rate');
                $rateElement->addChild('Currency', $currency);
            }

            // Update the value and request time
            $existingRate = $xml->xpath("//ExchangeRates[BaseCurrency = '$baseCurrency']/Rate[Currency = '$currency']")[0];
            $existingRate->Value = $rate;
            $timestamp = time();
            $existingRate->RequestTime = date('Y-m-d H:i:s', strtotime($timestamp));
        }
    } else {


        // Single conversion rate case
        $baseCurrency = $newRates['baseCurrency'];
        $conversionCurrency = $newRates['conversionCurrency'];
        $rate = $newRates['rate'];

        // Check if the base currency exists in the XML
        $existingBaseRates = $xml->xpath("//ExchangeRates[BaseCurrency = '$baseCurrency']");

        if (empty($existingBaseRates)) {
            // If the base currency doesn't exist, add it
            $baseRatesElement = $xml->Rates->addChild('ExchangeRates');
            $baseRatesElement->addChild('BaseCurrency', $baseCurrency);
        }

        // Check if the currency already exists in the XML
        $existingRate = $xml->xpath("//ExchangeRates[BaseCurrency = '$baseCurrency']/Rate[Currency = '$conversionCurrency']");

        if (empty($existingRate)) {
            // If the currency doesn't exist, add it
            $rateElement = $xml->xpath("//ExchangeRates[BaseCurrency = '$baseCurrency']")[0]->addChild('Rate');
            $rateElement->addChild('Currency', $conversionCurrency);
        }

        // Update the value and request time
        $existingRate = $xml->xpath("//ExchangeRates[BaseCurrency = '$baseCurrency']/Rate[Currency = '$conversionCurrency']")[0];
        $existingRate->Value = $rate;
        $existingRate->RequestTime = date('Y-m-d H:i:s', strtotime($newRates['timestamp']));
    }

    // Save updated XML to the file
    $xml->asXML($filename);
}


function convertCurrency($from, $to, $amnt, $format)
{
    $filename = 'exchange_rates.xml';

    if (empty($from) || empty($to) || empty($amnt) || empty($format)) {
        return generateErrorResponse(1000, "Required parameter is missing");
    }

    if ($format !== 'xml' && $format !== 'json') {
        return generateErrorResponse(1400, "Format must be xml or json");
    }

    if (file_exists($filename)) {
        $xml = simplexml_load_file($filename);

        if (isset($xml->ExchangeRates) && count($xml->ExchangeRates) > 0) {
            $timestamp = strtotime($xml->ExchangeRates[0]->Rate[0]->RequestTime);

            if (areRatesValid($timestamp)) {
                $rateAmount = findExchangeRate($xml, $from, $to);
            } else {
                $dataRates = fetchExchangeRates($from, $to, "conversion");
                $newRates = $dataRates['dummyData'];
                $xml = new SimpleXMLElement($xml->asXML());

                updateRatesInXml($xml, $newRates['rates'], $from);
            }
        } else {
            $dataRates = fetchExchangeRates($from, $to, "fetching");
            $newRates = json_encode($dataRates['dummyData']['rates']); // Assuming 'dummyData' is the correct key
            $xml = new SimpleXMLElement($xml->asXML());
            updateRatesInXml($xml, json_decode($newRates, true), $from);
        }
    } else {
        echo "XML file $filename not found\n";
    }

    // Currency validation
    $xmlString = file_get_contents('./currency_list.xml');
    $xml = simplexml_load_string($xmlString);
    $validCurrencies = $xml->xpath('//CcyNtry[Ccy != "N.A." and not(Ccy = preceding-sibling::Ccy)]/Ccy');

    $query = "//CcyNtry[Ccy = '$from' or Ccy = '$to']";
    $currencies = $xml->xpath($query);

    $fromCurrencyDetails = null;
    $toCurrencyDetails = null;

    foreach ($currencies as $currency) {
        $currencyCode = (string)$currency->Ccy;
        if ($currencyCode == $from) {
            $fromCurrencyDetails = [
                'code' => $currencyCode,
                'curr' => (string)$currency->CcyNm,
                'loc' => (string)$currency->CtryNm,
            ];
        } elseif ($currencyCode == $to) {
            $toCurrencyDetails = [
                'code' => $currencyCode,
                'curr' => (string)$currency->CcyNm,
                'loc' => (string)$currency->CtryNm,
            ];
        }
    }

    if (!in_array($from, $validCurrencies) || !in_array($to, $validCurrencies)) {
        return generateErrorResponse(1200, "Currency type not recognized");
    }

    if (!is_numeric($amnt) || $amnt <= 0) {
        return generateErrorResponse(1300, "Currency amount must be a decimal number");
    }

    $rateTime = new DateTime();
    $result = [
        "at" => $rateTime->format('Y-m-d H:i:s'),
        'rate' => isset($rateAmount) ? $rateAmount : '',
        'from' => [
            'code' => $fromCurrencyDetails['code'],
            'curr' => $fromCurrencyDetails['curr'],
            'loc' => $fromCurrencyDetails['loc'],
            'Amount' => $amnt,
        ],
        'to' => [
            'code' => $toCurrencyDetails['code'],
            'curr' => $toCurrencyDetails['curr'],
            'loc' => $toCurrencyDetails['loc'],
            'ConvertedAmount' => isset($rateAmount) ? floatval($amnt) * floatval($rateAmount) : '',
        ]
    ];

    if ($format === 'json') {
        return json_encode($result);
    } else {
        return arrayToXml($result);
    }
}



function jsonEncode($array)
{
    $jsonArray = ['conv' => $array];
    return json_encode($jsonArray, JSON_PRETTY_PRINT); // You can remove JSON_PRETTY_PRINT if you want a compact JSON string
}


function arrayToXml($array, $rootElement = 'conv', $xml = null)
{
    if ($xml === null) {
        $xml = new SimpleXMLElement('<' . $rootElement . '/>');
    }

    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $child = $xml->addChild($key);

            foreach ($value as $subKey => $subValue) {
                if (is_array($subValue)) {
                    $subChild = $child->addChild($subKey);
                    foreach ($subValue as $innerKey => $innerValue) {
                        $subChild->addChild($innerKey, htmlspecialchars($innerValue ?? ''));
                    }
                } else {
                    $child->addChild($subKey, htmlspecialchars($subValue ?? ''));
                }
            }
        } else {
            $xml->addChild($key, htmlspecialchars($value ?? ''));
        }
    }

    return $xml->asXML();
}

function fetchNewRateFromAPI($currencyCode)
{

    // Fetch data from the API


    $apiResponse = '{"rate": 1.3345}';

    // Check if the API request was successful
    if ($apiResponse === false) {
        return false;
    }

    // Decode the JSON response
    $responseData = json_decode($apiResponse, true);

    // Check if the JSON decoding was successful
    if ($responseData === null || !isset($responseData['rate'])) {
        return false;
    }

    return $responseData['rate'];
}



function createCurrency($currencyCode)
{
    // Load XML file
    $xmlString = file_get_contents('./currency_list.xml');
    $xml = simplexml_load_string($xmlString);
    if ($xml === null) {
        // Handle the error, e.g., the XML is not well-formed
        echo '<error>Failed loading XML</error>';
    }


    $existingCurrencies = $xml->CcyTbl->CcyNtry;


    foreach ($existingCurrencies as $existingCurrency) {
        // Check if the currency code matches
        if ($existingCurrency->Ccy == $currencyCode) {
            // Perform actions with $existingCurrency

            $existingCurrencyCode = $existingCurrency->Ccy;
        }
    }

    // If the currency exists, return an error
    if (!empty($existingCurrencyCode)) {
        return "<error>Currency $existingCurrencyCode already exists. Cannot create.</error>";
    }

    // If the currency doesn't exist, create a new node
    $newCurrency = $xml->CcyTbl->addChild('CcyNtry');
    $newCurrency->addChild('Ccy', $currencyCode);

    // Save the updated XML file
    $xml->asXML('./currency_list.xml');

    // Construct the XML response
    $responseXML = new SimpleXMLElement('<action></action>');
    $responseXML->addAttribute('type', 'post');
    $action = $responseXML->addChild('curr');
    $action->addChild('at', date('d M Y H:i:s'));
    $action->addChild('code', $currencyCode);

    // Return the XML response as a string
    return $responseXML->asXML();
}

function updateCurrency($currencyCode)
{
    // Load XML file
    $xmlFilePath = './exchange_rates.xml';
    $xmlString = file_get_contents($xmlFilePath);
    $xml = simplexml_load_string($xmlString);

    if ($xml === false) {
        // Handle the error, e.g., the XML is not well-formed
        return generateErrorResponse('update_failed', 'Failed loading exchange rates XML');
    }

    // Find the currency in the currency list
    $currencyListFilePath = './currency_list.xml';
    $currencyListXmlString = file_get_contents($currencyListFilePath);
    $currencyListXml = simplexml_load_string($currencyListXmlString);

    if ($currencyListXml === false) {
        // Handle the error, e.g., the XML is not well-formed
        return generateErrorResponse('update_failed', 'Failed loading currency list XML');
    }

    $currencyExists = false;
    foreach ($currencyListXml->CcyTbl->CcyNtry as $currencyEntry) {
        if ((string) $currencyEntry->Ccy == $currencyCode) {
            $currencyExists = true;
            break;
        }
    }

    if (!$currencyExists) {
        // Construct the XML error response
        $errorXML = new SimpleXMLElement('<error></error>');
        $errorXML->addAttribute('code', '404');
        $errorXML->addChild('message', 'Currency not found in currency list');
    
        // Return the XML error response as a string
        return $errorXML->asXML();
    }
    

    // Check if rates for this currency already exist and are up-to-date
    foreach ($xml->ExchangeRates as $exchangeRates) {
        if ((string) $exchangeRates->BaseCurrency == $currencyCode) {
            $latestRateTimestamp = strtotime($exchangeRates->Rate[0]->RequestTime);
            if (!areRatesValid($latestRateTimestamp)) {
                return currencyAlreadyUpToDateResponse();
            } else {
                // Fetch new rates
                $newRates = fetchExchangeRates($currencyCode, 'rates');

                // Update rates in XML
                updateRatesInXml($xml, $newRates['dummyData']['rates'], $currencyCode);

                // Return success message
                return '<info>Exchange rates are upto date</info>';
            }
        }
    }

    // If rates for this currency don't exist, fetch new rates and add them to XML
    $newRates = fetchExchangeRates($currencyCode, 'rates');

    updateRatesInXml($xml, $newRates['dummyData']['rates'], $currencyCode);

    return '<info>Exchange rates updated successfully</info>';
}


function deleteCurrency($currencyCode)
{
    // Load XML file
    $xmlFilePath = './currency_list.xml';
    $xmlString = file_get_contents($xmlFilePath);
    $xml = simplexml_load_string($xmlString);

    if ($xml === false) {
        // Handle the error, e.g., the XML is not well-formed
        return '<error>Failed loading XML</error>';
    }

    // Find the currency node to delete
    $currencyNode = $xml->xpath("//CcyNtry[Ccy = '$currencyCode']");

    // If the currency node exists, remove it
    if (!empty($currencyNode)) {
        $dom = dom_import_simplexml($currencyNode[0]);
        $dom->parentNode->removeChild($dom);

        // Save the updated XML file
        $xml->asXML($xmlFilePath);

        // Construct the XML response
        $responseXML = new SimpleXMLElement('<action></action>');
        $responseXML->addAttribute('type', 'del');
        $responseXML->addChild('at', date('d M Y H:i:s'));
        $responseXML->addChild('code', $currencyCode);

        // Return the XML response as a string
        return $responseXML->asXML();
    } else {
        // Return error message if the currency doesn't exist
        return "<error>Currency '$currencyCode' not found. Cannot delete.</error>";
    }
}
