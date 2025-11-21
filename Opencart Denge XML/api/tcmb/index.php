<?php
header('Content-Type: application/json; charset=utf-8');

require_once("api.php"); // Sende sınıfın adı API, dosya adı api.php

$api = new API();

// Tek bir döviz istendiyse
if (isset($_GET['doviz'])) {

    $d = strtoupper($_GET['doviz']);

    // USD, EUR, GBP, TL vs.
    $result = $api->getCurrency($d);

    echo $result;
    exit;

}

// Hiç parametre yoksa → tüm dövizleri döndür
echo $api->getCurrencys();
