<?php declare(strict_types=1);

require_once('vendor/autoload.php');

// STEP 1: Convert PDF to text
$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile(dirname(__FILE__) . '/Cept_Laenderliste.pdf');

// STEP 2: Convert pages to sections
$sections = array();
foreach ($pdf->getPages() as $idx=>$page) {
    $pageText = $page->getText();
    // fix encoding
    $pageText = str_replace(' ', ' ', $pageText);
    $matches = array();
    if (preg_match('/^ *(\d+) *\n *\n([A-Za-zç\-–,][A-Za-zç \-–,0-9]+) *\n *\n/', $pageText, $matches)) {
        $shortenedPageText = substr($pageText, strlen($matches[0]));
        $sections[] = array(
            'pages' => array($idx),
            'index' => $matches[1],
            'title' => $matches[2],
            'text' => $shortenedPageText,
            'pageText' => array($pageText),
        );
    } else if (count($sections)) {
        $sections[count($sections) - 1]['pages'][] = $idx;
        $sections[count($sections) - 1]['text'] .= $pageText;
        $sections[count($sections) - 1]['pageText'][] = $pageText;
    }
}

// STEP 3: Some minor corrections to sections
$firstSection = $sections[0]['pageText'][0];
$matches = array();
preg_match('/^ *\d+ *\n *\nInternational Affairs *\n *\n([A-Za-zç\-–,][A-Za-zç \-–,0-9]+) *\n/', $firstSection, $matches);
$sections[0]['title'] = $matches[1];
$sections[0]['text'] = substr($firstSection, strlen($matches[0]));
/*foreach ($sections as $idx=>&$section) {
}*/
//var_dump($sections);die();

// STEP 4: Parse data
$data = array();
foreach ($sections as $idx=>$section) {
    if (trim($section['title']) == 'IARU Region 1 Band Plan') {
        break;
    }
    $pageText = $section['text'];

    // parse implementation row
    $implementationMatches = array();
    if (!preg_match('/Implementation([\w\W]*)Call sign/', $pageText, $implementationMatches)) {
        echo 'Implementations parsing error' . PHP_EOL;
        var_dump($section);
        die();
    }
    $implementations = preg_split('/[^A-Za-z0-9,\/()]{2,}/', trim($implementationMatches[1]));
    $ceptAccepted = false;
    $ceptNoviceAccepted = false;
    $ceptGuestRequired = false;
    $ceptNoviceGuestRequired = false;
    $ceptRemarks = array();
    $ceptNoviceRemarks = array();
    if ($implementations[0] == '1') {
        $ceptRemarks[] = 1;
        $ceptNoviceRemarks[] = 1;
        array_shift($implementations);
    }
    if ($implementations[1] == '1') {
        $ceptRemarks[] = 1;
        unset($implementations[1]);
    }
    if (count($implementations) > 4 && $implementations[3] == '1') {
        $ceptRemarks[] = 1;
        unset($implementations[3]);
    }
    if ($implementations[0] == 'T/R 61-01 implemented') {
        $ceptAccepted = true;
        array_shift($implementations);
    }
    if ($implementations[0] == 'T/R 61-01 implemented, but guest licence') {
        $ceptAccepted = true;
        $ceptGuestRequired = true;
        array_shift($implementations);
        unset($implementations[1]);
    }
    $implementations = trim(str_replace(' ', '', implode(' ', $implementations)));
    if (strpos($implementations, 'ECC/REC/(05)06implemented') === 0) {
        $ceptNoviceAccepted = true;
        $implementations = substr($implementations, 25);
    }
    if (substr($implementations, -1, 1) == '1') {
        $ceptNoviceRemarks[] = 1;
        $implementations = trim(substr($implementations, 0, -1));
    }
    if ($implementations == 'ECC/REC/(05)06notimplemented') {
        $implementations = '';
    }
    if ($implementations == 'ECC/REC/(05)06notimplemented,butCEPTNoviceLicenceacceptedwithoutguestlicence') {
        $ceptNoviceAccepted = true;
        $implementations = '';
    }
    if (!empty($implementations)) {
        $ceptNoviceAccepted = true;
        $ceptNoviceGuestRequired = true;
    }

    // parse call sign extensions line
    $callsignMatches = array();
    if (!preg_match('/Call sign[\w\W]*Extensions/', $pageText, $callsignMatches)) {
        echo 'Callsign error!' . PHP_EOL;
        var_dump($section);
        die();
    }
    $ceptCallsigns = array();
    $ceptNoviceCallsigns = array();
    $lines = array();
    foreach (explode("\n", $callsignMatches[0]) as $line) {
        if (trim($line) == 'Extensions') {
            break;
        }
        if (preg_match('/^\d/', $line) || !preg_match('/^(?:Call sign)?\W*[A-Z0-9Ø]+\//', $line)) {
            $lines[count($lines) - 1] .= $line;
        } else {
            $lines[] = $line;
        }
    }
    foreach ($lines as $line) {
        $callsignMatch = array();
        if (!preg_match('/(?:Call sign)?\W+([A-Z0-9Ø]+)\/\W+([A-Za-z ]+[a-z]\W+|)(?:([A-Z0-9Ø]+)\/\W+([A-Za-z ]+[a-z]|)|()())?/', $line, $callsignMatch)) {
            echo 'Callsign error 2!' . PHP_EOL;
            var_dump($line);
            var_dump($section);
            die();
        }
        if (trim($callsignMatch[3]) != '') {
            $ceptCallsigns[] = array(
                'call' => trim($callsignMatch[1]),
                'region' => trim($callsignMatch[2]),
            );
        }
        if (count($callsignMatch) < 5) {
            var_dump($line);die();
        }
        if (trim($callsignMatch[3]) != '') {
            $ceptNoviceCallsigns[] = array(
                'call' => trim($callsignMatch[3]),
                'region' => trim($callsignMatch[4]),
            );
        }
    }

    // parse equivalent licenses
    $equivalentsMatches = array();
    if (!preg_match(
        '/Equivalent\W*([À-žA-Za-z0-9\/,:; ()]+)  +([À-žA-Za-z0-9\/,:; ()]+|)\W*national class\W*([À-žA-Za-z0-9\/,:; ()]+|)(  +[À-žA-Za-z0-9\/,:; ()]+| \n)\W*(\d\W*)?(?:^Band|Extensions)/m',
        $pageText,
        $equivalentsMatches
    )) {
        echo 'equivalent error' . PHP_EOL;
        var_dump($pageText);die();
        var_dump($pageText);
        echo 'Error on page ' . $idx . PHP_EOL;
        continue;
    }

    $ceptEquivalent = trim(trim($equivalentsMatches[1]) . ' ' . trim($equivalentsMatches[3]));
    $ceptNoviceEquivalent = trim(trim($equivalentsMatches[2]) . ' ' . trim($equivalentsMatches[4]));

    // parse notes
    $notesMatches = array();
    if (preg_match(
        '/Notes([\w\W]+)Info/',
        $pageText,
        $notesMatches
    )) {
        $notesMatches = preg_split('/\d+\W\W/', $notesMatches[1], PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    } else {
        $notesMatches = array();
    }

    foreach ($ceptRemarks as &$remark) {
        if (!isset($notesMatches[$remark])) {
            echo 'Could not find note #' . $remark . ' for section ' . $section['title'] . PHP_EOL;
            die();
        }
        $remark = $notesMatches[$remark];
    }
    foreach ($ceptNoviceRemarks as &$remark) {
        if (!isset($notesMatches[$remark])) {
            echo 'Could not find note #' . $remark . ' for section ' . $section['title'] . PHP_EOL;
            die();
        }
        $remark = $notesMatches[$remark];
    }

    // assemble licence data
    //echo /*trim($countryMatches[2]) . ': ' .*/ str_replace("\n", ' ', $implementations) . PHP_EOL;

    // merge structured data
    $countryParts = array();
    if (!preg_match('/^([A-Za-z, ]+)(?: – ([A-Za-z0-9ç, ]+))?$/', trim($section['title']), $countryParts)) {
        var_dump($countryParts);
        var_dump($section);
    }
    $data[] = array(
        'pageIndex' => $idx,
        'pageIndexRendered' => trim($section['index']),
        'country' => trim($countryParts[1]),
        'countryPart' => isset($countryParts[2]) ? trim($countryParts[2]) : '',
        'CEPT' => array(
            'accepted' => $ceptAccepted,
            'guestRequired' => $ceptGuestRequired,
            'callsignPrefixes' => $ceptCallsigns,
            'nationalEquivalent' => $ceptEquivalent,
            'remarks' => $ceptRemarks,
        ),
        'CEPT Novice' => array(
            'accepted' => $ceptNoviceAccepted,
            'guestRequired' => $ceptNoviceGuestRequired,
            'callsignPrefixes' => $ceptNoviceCallsigns,
            'nationalEquivalent' => $ceptNoviceEquivalent,
            'remarks' => $ceptNoviceRemarks,
        ),
    );
}
function bla($asdf) {
    $wer = array();
    foreach ($asdf as $key=>$value) {
        $wer[] = $value['call'] . ($value['region'] != '' ? ' (' . $value['region'] . ')' : '');
    }
    return $wer;
}
function outputHtml($data) {
    echo '<style>.yes{background-color:green;} .no{background-color:red}</style><table>
        <tr>
            <th>Country</th>
            <th>CEPT accepted</th>
            <th>CEPT novice accepted</th>
            <th>CEPT callsigns</th>
            <th>CEPT novice callsigns</th>
        </tr>';
    foreach ($data as $countryData) {
        echo '<tr>
            <td>' . $countryData['country'] . ($countryData['countryPart'] != '' ? ', ' : '') . $countryData['countryPart'] . '</td>
            <td class="' . ($countryData['CEPT']['accepted'] ? 'yes' : 'no') . '">
                ' . ($countryData['CEPT']['guestRequired'] ? 'Guest required' : '') . '
                ' . implode(', ', array_map(function($value, $key) {
                    return '<a href="#" title="' . $value . '">' . ($key + 1) . '</a>';
                }, $countryData['CEPT']['remarks'], array_keys($countryData['CEPT']['remarks']))) . '
            </td>
            <td class="' . ($countryData['CEPT Novice']['accepted'] ? 'yes' : 'no') . '">
                ' . ($countryData['CEPT Novice']['guestRequired'] ? 'Guest required' : '') . '
                ' . implode(', ', array_map(function($value, $key) {
                    return '<a href="#" title="' . $value . '">' . ($key + 1) . '</a>';
                }, $countryData['CEPT Novice']['remarks'], array_keys($countryData['CEPT Novice']['remarks']))) . '
            </td>
            <td>' . implode(',<br>', bla($countryData['CEPT']['callsignPrefixes'])) . '</td>
            <td>' . implode(',<br>', bla($countryData['CEPT Novice']['callsignPrefixes'])) . '</td>
        </tr>';
    }
    echo '</table>';
}
function outputJsonLicenses($countryData) {
    $licenses = array();
    foreach ($countryData as $data) {
        $licenses[$data['country']] = array();
        foreach ($data['CEPT']['callsignPrefixes'] as $callsign) {
            $licenses[$data['country']][$callsign['call']] = array(
                'usedForCept' => true,
                'usedForCeptNovice' => false,
                'region' => $callsign['region'],
            );
        }
        foreach ($data['CEPT Novice']['callsignPrefixes'] as $callsign) {
            if (!isset($licenses[$data['country']][$callsign['call']])) {
                $licenses[$data['country']][$callsign['call']] = array(
                    'usedForCept' => false,
                    'usedForCeptNovice' => true,
                    'region' => $callsign['region'],
                );
            } else {
                $licenses[$data['country']][$callsign['call']]['usedForCeptNovice'] = true;
            }
        }
    }
    echo json_encode($licenses);
}
function outputJsonCeptAcceptance($countryData) {
    $ceptAcceptance = array();
    // TODO: squash callsign prefixes
    foreach ($countryData as $data) {
        $ceptAcceptance[$data['country']] = array(
            'cept' => $data['CEPT'],
            'ceptNovice' => $data['CEPT Novice'],
        );
    }
    echo json_encode($ceptAcceptance);
}
outputHtml($data);
// TODO: $data is incomplete. examples: turkey, usa, uk
// TODO: $data is inconsistent in country names. ISO code should be used
//outputJsonLicenses($data);
//outputJsonCeptAcceptance($data);
//var_dump($data);
