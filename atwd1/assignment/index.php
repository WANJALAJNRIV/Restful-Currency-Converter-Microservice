<?php

// Include necessary files

use function PHPSTORM_META\type;

require_once 'app_functions.php';




if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Parse parameters
    $from = isset($_GET['from']) ? $_GET['from'] : '';
    $to = isset($_GET['to']) ? $_GET['to'] : '';
    $amnt = isset($_GET['amnt']) ? $_GET['amnt'] : '';
    $format = isset($_GET['format']) ? $_GET['format'] : 'xml';

    // Validate parameters and perform currency conversion logic
    $result = convertCurrency($from, $to, $amnt, $format);

    if (is_array($result) || is_object($result)) {
        print_r($result);
        // Return response based on format
        if ($format === 'json') {
            header('Content-Type: application/json');
           
            echo json_encode($result, JSON_PRETTY_PRINT);
        } else {
            header('Content-Type: application/xml');
            echo arrayToXml($result);
        }
    } else {
        header('Content-Type: application/xml');
        $xml = simplexml_load_string($result);
        echo $xml;
        echo $result;
    }
}

// Handle PUT requests
else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Parse parameters from the request body
    $currentUrl = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    // Parse the URL to get parameters
    $urlParts = parse_url($currentUrl);
    parse_str($urlParts['query'], $queryParams);

    // Extract cur and action parameters
    $currencyCode = isset($queryParams['cur']) ? $queryParams['cur'] : '';
    $action = isset($queryParams['action']) ? $queryParams['action'] : '';

    // Validate parameters and perform update logic
    $result = updateCurrency($currencyCode);

    header('Content-Type: application/raw');
    echo $result;
}

// Handle POST requests
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse parameters from the request body
    $currentUrl = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    // Parse the URL to get parameters
    $urlParts = parse_url($currentUrl);
    parse_str($urlParts['query'], $queryParams);

    // Extract cur and action parameters
    $currencyCode = isset($queryParams['cur']) ? $queryParams['cur'] : '';
    $action = isset($queryParams['action']) ? $queryParams['action'] : '';

    $actionResult = createCurrency($currencyCode);
    header('Content-Type: application/xml');
    echo $actionResult;
}
// Handle DELETE requests
else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get the current URL
    $currentUrl = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    // Parse the URL to get parameters
    $urlParts = parse_url($currentUrl);
    parse_str($urlParts['query'], $queryParams);

    // Extract cur parameter
    $currencyCode = isset($queryParams['cur']) ? $queryParams['cur'] : '';

    // Validate parameters and perform delete logic
    $result = deleteCurrency($currencyCode);
    header('Content-Type: application/xml');
    echo $result;
}

// Handle other request methods or show an error for unsupported methods
else {
    http_response_code(405); // Method Not Allowed
    echo "Unsupported HTTP method.";
}
