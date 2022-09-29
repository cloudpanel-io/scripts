<?php

error_reporting((E_ALL | E_STRICT) ^ E_NOTICE);
ini_set('display_errors', 1);

try {

    $muha = 1;

} catch (\Exception $e) {
    $errorMessage = sprintf('An error occurred: %s', $e->getMessage());
    echo $errorMessage;
}