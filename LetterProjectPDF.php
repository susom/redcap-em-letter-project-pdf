<?php

namespace Stanford\LetterProjectPDF;


use REDCap;
use Files;

class LetterProjectPDF extends \ExternalModules\AbstractExternalModule
{

    public static $config;



    function redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        //request from VJ to enlarge resize font in survey top right corner
         //$this->emDebug("Starting redcap_survey_page_top", $instrument);

        ?>

        <style>

            #changeFont {
                font-size: 20px;
            }

            .increaseFont img {
                width: 40px;
            }

            .decreaseFont img {
                width: 40px;
            }

        </style>
        <?php

    }

    function redcap_survey_complete($project_id, $record = NULL, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        if ($instrument == $this->getProjectSetting('last-survey')) {
            //last survey done so rediret to the pdf
            $letter_url = $this->getUrl("GetLetter.php", true,true);
            $redirect_url = $letter_url."&action=P&record=".$record;
            header("Location: " . $redirect_url);
        }
    }

    public function getResponseData($id, $select = null, $event = null)
    {
        // get  responses for this ID and event
        $q = REDCap::getData('array', $id, $select, $event);

        if (count($q) == 0) {
            $this->emError($q, "Unable to get responses for $id in event $event", "ERROR");
            return false;
        }
        return $q;
    }


    function setupLetter($record_id)
    {
        global $module;

        set_time_limit(0);

        //$pdf = new LetterPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT,true, 'UTF-8', false);
        $pdf = new LetterPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, false, 'ISO-8859-1', false);


        // Set document information dictionary in unicode mode
        $pdf->SetDocInfoUnicode(true);

        //$module->emDebug("LOGO", PDF_HEADER_LOGO);
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Stanford What-Matters-Most Letter Directive', null, array(150, 43, 40));

        // set header and footer fonts
        $pdf->setHeaderFont(Array('times', '', 14));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        $pdf->SetFont('arial', '', 12);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // ---------------------------------------------------------
        //if record ID is set, get Data for that record

        // Use alternative passing of parameters as an associate array
        $params = array(
            'project_id' => $module->getProjectId(),
            'return_format' => 'json',
            //'exportSurveyFields'=>true,
            //'fields'=>array('dob','record_id'),
            //'events' => array($module->getProjectSetting('final-event')),
            'records' => array($record_id));
        $data = REDCap::getData($params);

        //$q = \REDCap::getData($module->getProjectId(), 'json',  array($record_id), null, $module->getProjectSetting('final-event'));
        $final_data = json_decode($data, true);

        //
        $final_data = current($final_data);
        //$module->emDebug($params,$module->getProjectId(),$module->getProjectSetting('final-event'), $final_data, $record_id, "FINAL DATA");

        $pdf->CustomHeaderText =$final_data['ltr_name'];

        // ---------------------------------------------------------

        // set font
        //$pdf->SetFont('arial', '', 12);

        // add a page
        $pdf->AddPage();

        //create html for page 1
        $html = $pdf->makeHTMLPage1($record_id, $final_data);
        $pdf->writeHTML($html, true, false, true, false, '');

        //create html for page 2
        $pdf->AddPage();
        $html = $pdf->makeHTMLPage2($record_id, $final_data);
        $pdf->writeHTML($html, true, false, true, false, '');

        //Question 6
        $q6 = $final_data['q6'];
        $pdf->RadioButton('health_decisions', 5, array(), array(), '1', ($q6 == 1));
        $pdf->Cell(35, 5, 'Starting right now');
        $pdf->Ln(6);
        $pdf->RadioButton('health_decisions', 5, array(), array(), '2', $q6 == 2);
        $pdf->Cell(35, 5, 'When I am not able to make decisions by myself');
        $pdf->Ln(6);

        //create html for page 3
        $pdf->AddPage();
        $pdf = $pdf->makeHTMLPage3($record_id, $final_data, $pdf);

        //sometimes the table is longer, so split tables into two pages
        $pdf->AddPage();
        $pdf = $pdf->makeHTMLPage3Part2($record_id, $final_data, $pdf);

        //create html for page 4
        $pdf->AddPage();
        $pdf = $pdf->makeHTMLPage4($record_id, $final_data, $pdf);

        //copy over signatures for page 5
        $patient_sigfile_path = Files::copyEdocToTemp($final_data['patient_signature'], true);
        $adult_sigfile_path = Files::copyEdocToTemp($final_data['adult_signature'], true);

        //create html for page 5
        $pdf->AddPage();
        $html5 = $pdf->makeHTMLPage5($record_id, $final_data, $patient_sigfile_path, $adult_sigfile_path);
        $pdf->writeHTML($html5, true, false, true, false, '');

        //unlink the files
        unlink($patient_sigfile_path);
        unlink($adult_sigfile_path);

        //copy over signatures for page 6
        $witness1_sigfile_path = Files::copyEdocToTemp($final_data['witness1_signature'], true);
        $witness2_sigfile_path = Files::copyEdocToTemp($final_data['witness2_signature'], true);


        //create html for page 6n

        $pdf->AddPage();
        $html6 = $pdf->makeHTMLPage6($record_id, $final_data, $witness1_sigfile_path, $witness2_sigfile_path);
        $pdf->writeHTML($html6, true, false, true, false, '');

        //unlink the files
        unlink($witness1_sigfile_path);
        unlink($witness2_sigfile_path);


        $declaration_sigfile_path = Files::copyEdocToTemp($final_data['declaration_signature'], true);
        $specialwitness_sigfile_path = Files::copyEdocToTemp($final_data['specialwitness_signature'], true);

        //create html for page 7
        $pdf->AddPage();
        $html7 = $pdf->makeHTMLPage7($record_id, $final_data, $declaration_sigfile_path, $specialwitness_sigfile_path);
        $pdf->writeHTML($html7, true, false, true, false, '');

        unlink($declaration_sigfile_path);
        unlink($specialwitness_sigfile_path);

        return $pdf;
    }


    /**
     *
     * emLogging integration
     *
     */
    function emLog()
    {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "INFO");
    }

    function emDebug()
    {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || (!empty($_GET['pid']) && $this->getProjectSetting('enable-project-debug-logging'))) {
            $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
            $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function emError()
    {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }
}