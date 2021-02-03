<?php

/**
 * Parses a CSV file and outputs a JSON file
 * Inteded for command line use!
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

// normalize data
$dataOut = array();
$status = array(
    0 => 'planned',
    1 => 'qrv',
    2 => 'qrx',
    3 => 'qrt',
);
foreach ($data as $idx=>$repeater) {
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
        'latitude' => (float) $repeater['lat'],
        'longitude' => (float) $repeater['lon'],
        'lastUpdate' => '2020-10-08',
        'modes' => getModes($repeater['Remarks']),
    );
    /*$data[$idx]['Type'] = 'Voice';
    $data[$idx]['LastUpdate'] = '2020-10-08';
    $remarks = $repeater['Remarks'];
    $modes = getModes($remarks);
    $data[$idx]['Modes'] = $modes;*/
    // TODO: Check if modes are completely parsed!
    // TODO: Try to parse links to other relais
    /*echo $remarks . "\t\t";
    foreach ($modes as $mode) {
        echo $mode['type'] . '(' . $mode['addr'] . '),';
    }
    echo PHP_EOL;*/
}

// output JSON
echo json_encode($dataOut, JSON_PRETTY_PRINT);
