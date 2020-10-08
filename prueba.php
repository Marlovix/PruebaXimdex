<?php

if (!ini_get("register_argc_argv")) {
    die("Option 'register_argc_argv' must be enabled." . PHP_EOL);
}

if ($argc != 3) {
    die("The number of parameters are not correct (index.php *.csv *.json)." . PHP_EOL);
}

define('CATEGORY', 'CATEGORY');
define('COST', 'COST');
define('QUANTITY', 'QUANTITY');

$salesName = $argv[1];
$pricesName = $argv[2];

$salesFile = null;
$pricesFile = null;

// Checking the files added as parameters
try {
    $salesFile = checkFile($salesName);
} catch (Exception $e) {
    die("(" . $salesName . ") " . $e->getMessage() . PHP_EOL);
}

try {
    $pricesFile = checkFile($pricesName);
    fclose($pricesFile); // Can be closed because it is a json file
} catch (Exception $e) {
    die("(" . $pricesName . ") " . $e->getMessage() . PHP_EOL);
}

// Get an array with the category as key and the formula to get the sale price as value
$jsonData = json_decode(file_get_contents($pricesName), true);
if (!array_key_exists("categories", $jsonData)) {
    die("The json file is not correct." . PHP_EOL);
}
$prices = $jsonData['categories'];

$categoryIndex = 0;
$costIndex = 0;
$quantityIndex = 0;

$line = 1;
$profit = [];
while (($saleLine = fgetcsv($salesFile, 0, ";")) !== FALSE) {

    // First line has the header of CSV file, so indexes of required columns are taken
    if ($line == 1) {
        $categoryIndex = getColumnIndex($saleLine, CATEGORY);
        $costIndex = getColumnIndex($saleLine, COST);
        $quantityIndex = getColumnIndex($saleLine, QUANTITY);
    } else {
        if (array(null) !== $saleLine) { // ignore blank lines

            // Getting data from CSV line
            $category = $saleLine[$categoryIndex];
            $cost = floatval(str_replace(',', '.', str_replace('.', '', mb_substr($saleLine[$costIndex], 0, -1))));
            $quantity = str_replace('.', '', $saleLine[$quantityIndex]);

            // Init each category with no profit
            if (!array_key_exists($category, $profit)) {
                $profit[$category] = 0;
            }

            // Getting formula to get sale price depending on category
            $formula = "";
            if (array_key_exists($category, $prices)) {
                $formula = $prices[$category];
            } else {
                $formula = $prices["*"];
            }

            // Calculate the profit and sum to the category
            $categoryProfit = parseProfitFormula($formula, $cost);
            $profit[$category] += $categoryProfit * $quantity;
        }
    }
    $line++;
}
fclose($salesFile);

// Showing the result //

foreach ($profit as $category => $result) {
    echo $category . ": " . number_format($result, 3, ",", ".") . PHP_EOL;
}

/**
 * Funtions
 */

function getColumnIndex($columns, $value)
{
    foreach ($columns as $index => $column) {
        if ($column == $value) {
            return $index;
        }
    }
    return -1;
}

function checkFile($nameFile)
{
    if (!file_exists($nameFile)) {
        throw new Exception('File not found.');
    }

    $file = fopen($nameFile, "r");
    if (!$file) {
        throw new Exception('File open failed.');
    }

    return $file;
}

function parseProfitFormula($formula, $value)
{
    $currentValue = $value;

    $result = 0;
    $firstChar = 0;
    $characters = str_split_unicode($formula);
    foreach ($characters as $index => $character) {
        if ($character == '%') {
            $result += $currentValue * floatval(mb_substr($formula, $firstChar, $index - $firstChar)) / 100;
            $firstChar = $index + 1;
        } else if ($character == '€') {

            $number = floatval(mb_substr($formula, $firstChar, $index - $firstChar));
            $result += $number;

            // This is neccesary for the case '..€..%'
            $currentValue += $number;

            $firstChar = $index + 1;
        }
    }

    return $result;
}

function str_split_unicode($str, $l = 0)
{
    if ($l > 0) {
        $ret = array();
        $len = mb_strlen($str, "UTF-8");
        for ($i = 0; $i < $len; $i += $l) {
            $ret[] = mb_substr($str, $i, $l, "UTF-8");
        }
        return $ret;
    }
    return preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
}
