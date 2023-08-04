<?php
namespace Stanford\LetterProjectPDF;

/** @var \Stanford\LetterProjectPDF\LetterProjectPDF $module */

include_once "LetterPDF.php";
use Stanford\LetterProject\LetterPDF;
use REDCap;


if (!empty($_REQUEST['record'])) {
    $record_id = $_REQUEST['record'];
    $module->emDebug("RECORD ID is $record_id");
} else {
    die("Please check this link from the context of a record.");
}

$date = date('Ymd_his', time());
$fname = 'LETTER_PROJECT_'.$record_id_.'_' . $date . '.pdf';

$pdf = $module->setupLetter($record_id);


$action = $_REQUEST['action'];

switch ($action) {
    case 'P':
        $pdf->IncludeJS("print();");
        $pdf->Output($fname, 'I');
        break;
    case 'D':
        $pdf->Output($fname, 'D');
        break;
    default:
        $pdf->Output($fname, 'I');
}
