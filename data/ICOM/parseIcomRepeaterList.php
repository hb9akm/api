<?php declare(strict_types=1);

require_once(dirname(__DIR__) . '/Library.php');

/**
 * Parses a CSV file and outputs a JSON file
 * Intended for command line use!
 *
 * php parseCHRepeaterList.php <inputFile.csv> | <outputFile.json>
 */

if (count($argv) < 2) {
    die('Usage: php parseCHRepeaterList.php <inputFile.csv> | <outputFile.json>');
}
if (!file_exists($argv[1])) {
    die('File does not exist');
}
// read CSV line by line
$file = new \SplFileObject($argv[1], 'r');
$headers = array();
$data = array();
while ($line = $file->fgets()) {
    if (empty($line)) {
        continue;
    }
    if (!count($headers)) {
        $headers = str_getcsv($line);
        continue;
    }
    $data[] = array_combine($headers, str_getcsv($line));
}

function getCountryIso2Code(&$lon, &$lat, $fallbackName) {
    static $countries = array();

    if (isset($countries[$lon . '/' . $lat])) {
        return $countries[$lon . '/' . $lat];
    }
    if ($lon != 0 || $lat != 0) {
        $res = getJson(
            'https://nominatim.openstreetmap.org/reverse?lat=' . $lat . '&lon=' . $lon . '&format=json&zoom=3'
        );
    }
    if (!isset($res->address) || !isset($res->address->country_code)) {
        echo 'Fallback resolution' . PHP_EOL;
        $res = getJson(
            'https://nominatim.openstreetmap.org/search?q=' . $fallbackName . '&format=json&addressdetails=1&limit=1'
        );
        $res = current($res);
        if (!$res || !isset($res->address) || !isset($res->address->country_code)) {
            echo 'Failed to get country code for ' . $lon . '/' . $lat . PHP_EOL;
            return 'XX';
            die();
        }
        if ($lon == 0 && $lat == 0) {
            echo 'Fixing lon & lat!' . PHP_EOL;
            $lon = (float) $res->lon;
            $lat = (float) $res->lat;
        }
    }
    $res = strtoupper($res->address->country_code);
    $countries[$lon . '/' . $lat] = $res;
    return $res;
}

$dataOut = array();
foreach ($data as $idx=>$repeater) {
    $shiftPositive = true;
    if ($repeater['Dup'] == 'DUP-') {
        $shiftPositive = false;
    } else if ($repeater['Dup'] != 'DUP+') {
        echo 'invalid DUP value for idx ' . $idx . ': ' . $repeater['Dup'] . PHP_EOL;
        continue;
    }
    if ($shiftPositive) {
        $qrgRx = $repeater['Frequency'] + $repeater['Offset'];
    } else {
        $qrgRx = $repeater['Frequency'] - $repeater['Offset'];
    }
    $lon = (float) $repeater['Longitude'];
    $lat = (float) $repeater['Latitude'];
    $countryCode = getCountryIso2Code(
        $lon,
        $lat,
        $repeater['Name']
    );
    // Skip CH repeaters as we got better data for this
    if ($countryCode == 'CH') {
        continue;
    }
    $dataOut[$idx] = array(
        'qrgTx' => (float) $repeater['Frequency'],
        'qrgRx' => (float) $qrgRx,
        'call' => explode(' ', $repeater['Repeater Call Sign'])[0],
        'qthName' => $repeater['Name'],
        'qthLocator' => lonLat2Locator(
            $lon,
            $lat
        ),
        'altitude' => null,
        'remarks' => '',
        'authority' => 'ICOM',
        'country' => $countryCode,
        'status' => 'qrv',
        'type' => 'voice',
        'latitude' => $lat,
        'longitude' => $lon,
        'locationPrecision' => array('locator'),
        'lastUpdate' => '2020-07-31',
        'modes' => array(
            array(
                'type' => 'FM',
                'addr' => !empty($repeater['TONE']) ? 'T' . $repeater['TONE'] : '',
            ),
        ),
    );
}

// output JSON
echo json_encode($dataOut, JSON_PRETTY_PRINT);
/*
    {
        "qrgTx": 29.65,
        "qrgRx": 29.55,
        "call": "HB9HD",
        "qthName": "Fronalpstock\/SZ",
        "qthLocator": "JN46HX",
        "altitude": 1904,
        "remarks": "NFM RX>HochYbrig",
        "authority": "USKA",
        "country": "CH",
        "status": "qrv",
        "type": "voice",
        "latitude": 46.9682706,
        "longitude": 8.6373811,
        "lastUpdate": "2020-10-08",
        "modes": [
            {
                "type": "NFM",
                "addr": ""
            }
        ]
    },
*
*/
