<?php declare(strict_types=1);

require_once(dirname(__DIR__) . '/Library.php');

/**
 * Parses a CSV file and outputs a JSON file
 * Intended for command line use!
 *
 * php parseRemarks.php <inputFile.csv> | <outputFile.json>
 */

if (count($argv) < 2) {
    die('Usage: php parseRemarks.php <inputFile.csv> | <outputFile.json>');
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

function getModes($remarks) {
    $modes = array();
    $matches = array();
    if (preg_match('/NFM(?: (T\d\d\d?\.\d|1750))?/', $remarks, $matches)) {
        $modes[] = array(
            'type'=> 'NFM',
            'addr'=> isset($matches[1]) ? $matches[1] : '',
        );
    }
    if (preg_match('/(?<![4N])FM(?: (T\d\d\d?\.\d|1750))?/', $remarks, $matches)) {
        $modes[] = array(
            'type'=> 'FM',
            'addr'=> isset($matches[1]) ? $matches[1] : '',
        );
    }
    if (preg_match('/C4FM(?: (#\d+))?/', $remarks, $matches)) {
        $modes[] = array(
            'type'=> 'C4FM',
            'addr'=> isset($matches[1]) ? $matches[1] : '',
        );
    }
    if (preg_match('/EL(?:(#\d+))?/', $remarks, $matches)) {
        $modes[] = array(
            'type'=> 'EL',
            'addr'=> isset($matches[1]) ? $matches[1] : '',
        );
    }
    if (preg_match('/(DMR\*?(?:-[A-Z])?(?: #\d+)?(?: CC\d)?)/', $remarks, $matches)) {
        $modes[] = array(
            'type'=> 'DMR',
            'addr'=> isset($matches[1]) ? $matches[1] : '',
        );
    }
    if (preg_match('/D-STAR\*?(?: (CCS#\d+))?/', $remarks, $matches)) {
        $modes[] = array(
            'type'=> 'D-STAR',
            'addr'=> isset($matches[1]) ? $matches[1] : '',
        );
    }
    return $modes;
}

function findBestLocation(
    string $location,
    string $locator,
    int $elevation,
    string &$precisionInfo = ''
): array {
    $precisionInfo = 'locator-only';
    $lonLat = locator2LonLat($locator);
    try {
        $nominatimResults = getJson(
            'https://nominatim.openstreetmap.org/search?format=json&q=' . $location
        );
    } catch (\Exception $e) {
        return $lonLat;
    }
    $bestResult = array();
    foreach ($nominatimResults as $nominatimResult) {
        $calculatedLocator = lonLat2Locator(
            (float) $nominatimResult->lon,
            (float) $nominatimResult->lat
        );
        if ($calculatedLocator != $locator) {
            continue;
        }
        // if we've got a match we're most likely more precise
        $precisionInfo = 'locator-improved';
        // first match is probably best (sort order of nominatim)
        if (!count($bestResult)) {
            $bestResult = array(
                'lon' => (float) $nominatimResult->lon,
                'lat' => (float) $nominatimResult->lat,
            );
            // todo: drop the following line as soon as the todo below is done
            return $bestResult;
        }
        continue;
        // todo: get height. if it's exact (or maybe within a margin): use it
        echo 'Getting height info for: ';
        echo 'https://api.open-elevation.com/api/v1/lookup?locations=' .
                $nominatimResult->lat . ',' .
                $nominatimResult->lon . PHP_EOL;
        $heightInfo = getJson(
            'https://api.open-elevation.com/api/v1/lookup?locations=' .
                $nominatimResult->lat . ',' .
                $nominatimResult->lon
        );
        var_dump($elevation);
        var_dump($heightInfo);die();
    }
    if (count($bestResult)) {
        return $bestResult;
    }
    return $lonLat;
}

// normalize data
$dataOut = array();
$status = array(
    0 => 'planned',
    1 => 'qrv',
    2 => 'qrx',
    3 => 'qrt',
);
foreach ($data as $idx=>$repeater) {
    $locatorLonLat = locator2LonLat($repeater['Locator']);
    $locationPrecision = '';
    $lonLat = findBestLocation(
        $repeater['QTH'],
        $repeater['Locator'],
        (int) substr($repeater['Alt.'], 0, -1),
        $locationPrecision
    );

    $dataOut[$idx] = array(
        'qrgTx' => (float) $repeater['QRG TX'],
        'qrgRx' => (float) $repeater['QRG RX'],
        'call' => $repeater['Call'],
        'qthName' => $repeater['QTH'],
        'qthLocator' => $repeater['Locator'],
        'altitude' => (int) substr($repeater['Alt.'], 0, -1),
        'remarks' => $repeater['Remarks'],
        'authority' => 'USKA',
        'country' => 'CH',
        'status' => $status[$repeater['Status']],
        'type' => 'voice',
        'latitude' => $lonLat['lat'],
        'longitude' => $lonLat['lon'],
        'locationPrecision' => $locationPrecision,
        'lastUpdate' => '2020-10-08',
        'modes' => getModes($repeater['Remarks']),
    );
    // TODO: Add parsing info property
    // TODO: Try to parse links to other relais
}

// output JSON
echo json_encode($dataOut, JSON_PRETTY_PRINT);
