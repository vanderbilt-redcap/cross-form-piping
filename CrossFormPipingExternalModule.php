<?php

namespace Vanderbilt\CrossFormPipingExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class CrossFormPipingExternalModule extends AbstractExternalModule
{
    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance = 1) {
        /*$source = $this->getProjectSetting('source');
        $instanceMatch = $this->getProjectSetting('instance-match');
        $destinations = $this->getProjectSetting('destination');
        $destValue = $this->getProjectSetting('dest-value');
        $recordData = \Records::getData($project_id,'array',array($record));
        $project = new \Project($project_id);
        echo "<pre>";
        print_r($source);
        echo "</pre>";
        echo "<pre>";
        print_r($instanceMatch);
        echo "</pre>";
        echo "<pre>";
        print_r($destinations);
        echo "</pre>";
        echo "<pre>";
        print_r($destValue);
        echo "</pre>";
        foreach ($source as $sIndex => $src) {
            $saveList = array();
                foreach ($destinations[$sIndex] as $index => $destinationField) {
                    $destinationInstrument = $project->metadata[$destinationField]['form_name'];
                    list($stringsToReplace,$fieldNamesReplace,$operators,$addList) = $this->parseLogicString($destValue[$sIndex][$index]);
                    echo "List of things:<br/>";
                    echo "<pre>";
                    print_r($stringsToReplace);
                    echo "</pre>";
                    echo "<pre>";
                    print_r($fieldNamesReplace);
                    echo "</pre>";
                    echo "<pre>";
                    print_r($operators);
                    echo "</pre>";
                    echo "<pre>";
                    print_r($addList);
                    echo "</pre>";
                }
        }*/
        /*$recordData = \Records::getData($project_id,'array',array($record));
        $project = new \Project($project_id);
        $string = "[date_enrolled]+test-string";

        list($stringsToReplace,$fieldNamesReplace,$operators,$addList) = $this->parseLogicString($string);
        $currentValue = "";
        // If all the data arrays are somehow empty, that is invalid
        if (empty($stringsToReplace) && empty($fieldNamesReplace) && empty($addList)) ExternalModules::exitAfterHook();
        foreach ($addList as $index => $addValue) {
            $replaceValue = "";
            if (in_array($addValue,$stringsToReplace)) {
                $addField = $fieldNamesReplace[array_keys($stringsToReplace,$addValue)[0]];
                $replaceInstrument = $project->metadata[$addField]['form_name'];
                if ($project->isRepeatingForm($event_id,$replaceInstrument)) {
                    $replaceValue = $recordData[$record]['repeat_instances'][$event_id][$replaceInstrument][$repeat_instance][$addField];
                }
                else {
                    $replaceValue = $recordData[$record][$event_id][$addField];
                }
            }
            else {
                $replaceValue = $addValue;
            }
            if ($index > 0 && $operators[$index - 1] != "") {
                $currentValue = $this->processOperator($currentValue,$operators[$index-1],$replaceValue);
            }
            else {
                $currentValue .= $replaceValue;
            }
        }*/
    }

    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
        $source = $this->getProjectSetting('source');
        $instanceMatch = $this->getProjectSetting('instance-match');
        $destinations = $this->getProjectSetting('destination');
        $destValue = $this->getProjectSetting('dest-value');
        $recordData = \Records::getData($project_id,'array',array($record));
        $project = new \Project($project_id);

        //ExternalModules::exitAfterHook();
        foreach ($source as $sIndex => $src) {
            $saveList = array();
            if (isset($_POST[$src]) && $_POST[$src] != "") {
                foreach ($destinations[$sIndex] as $index => $destinationField) {
                    $destinationInstrument = $project->metadata[$destinationField]['form_name'];
                    list($stringsToReplace,$fieldNamesReplace,$operators,$addList) = $this->parseLogicString($destValue[$sIndex][$index]);
                    $currentValue = "";
                    // If all the data arrays are somehow empty, that is invalid
                    if (empty($stringsToReplace) && empty($fieldNamesReplace) && empty($addList)) continue;
                    foreach ($addList as $aIndex => $addValue) {
                        $replaceValue = "";
                        if (strpos($addValue,":survey_link") !== false) {
                            $splitForm = explode(":",$fieldNamesReplace[array_keys($stringsToReplace,$addValue)[0]]);
                            $replaceValue = $this->generateSurveyLink($project,$splitForm[0],$record,$event_id,$repeat_instance,$instanceMatch[$sIndex],$recordData);
                        }
                        else if (in_array($addValue,$stringsToReplace)) {
                            $addField = $fieldNamesReplace[array_keys($stringsToReplace,$addValue)[0]];
                            $replaceInstrument = $project->metadata[$addField]['form_name'];
                            if ($project->isRepeatingForm($event_id,$replaceInstrument)) {
                                $replaceValue = $recordData[$record]['repeat_instances'][$event_id][$replaceInstrument][$repeat_instance][$addField];
                            }
                            else {
                                $replaceValue = $recordData[$record][$event_id][$addField];
                            }
                        }
                        else {
                            $replaceValue = $addValue;
                        }
                        if ($index > 0 && $operators[$aIndex - 1] != "") {
                            $currentValue = $this->processOperator($currentValue,$operators[$aIndex-1],$replaceValue);
                        }
                        else {
                            $currentValue .= $replaceValue;
                        }
                    }

                    if ($project->isRepeatingForm($event_id,$destinationInstrument)) {
                        if ($instanceMatch[$sIndex] != "") {
                            $saveList[$record]['repeat_instances'][$event_id][$destinationInstrument][$repeat_instance][$destinationField] = $currentValue;
                        }
                        else {
                            $maxInstance = (max(array_keys($recordData[$record]['repeat_instances'][$event_id][$destinationInstrument])) ? max(array_keys($recordData[$record]['repeat_instances'][$event_id][$destinationInstrument])) : 1);
                            $saveList[$record]['repeat_instances'][$event_id][$destinationInstrument][$maxInstance][$destinationField] = $currentValue;
                        }
                    }
                    else {
                        $saveList[$record][$event_id][$destinationField] = $currentValue;
                    }

                    if (!empty($saveList)) {
                        /*$changes[$recordID]['redcap_repeat_instance'] = $instance;
                        $changes[$recordID]['redcap_repeat_instrument'] = $notifForm;
                        $changes[$recordID][$this->getProjectSetting('unique-user')] = $user;*/
                        $result = \REDCap::saveData($project_id, 'array', $saveList, 'overwrite');
                        if (!empty($result['errors']) && $result['errors'] != "") {
                            $errorString = "";
                            foreach ($result['errors'] as $error) {
                                $errorString .= $error."<br/>";
                            }
                            throw new \Exception($errorString);
                        }
                    }
                }
            }
        }
    }

    function getCalculatedData($calcString,$recordData,$event_id,$project_id,$repeat_instrument,$repeat_instance=1) {
        $formatCalc = \Calculate::formatCalcToPHP($calcString);
        //echo "The string!!<br/>$formatCalc<br/>";
        $parser = new \LogicParser();
        try {
            list($funcName, $argMap) = $parser->parse($formatCalc, $event_id, true, false);
            $thisInstanceArgMap = $argMap;
            $Proj = new \Project($project_id);
            foreach ($thisInstanceArgMap as &$theseArgs) {
                $theseArgs[0] = $event_id;
            }
            //echo "Form: ".$Proj->metadata['age']['form_name']."<br/>";

            if ($repeat_instance != "") {
                foreach ($thisInstanceArgMap as &$theseArgs) {
                    // If there is no instance number for this arm map field, then proceed
                    if ($theseArgs[3] == "") {
                        $thisInstanceArgEventId = ($theseArgs[0] == "") ? $event_id : $theseArgs[0];
                        $thisInstanceArgEventId = is_numeric($thisInstanceArgEventId) ? $thisInstanceArgEventId : $Proj->getEventIdUsingUniqueEventName($thisInstanceArgEventId);
                        $thisInstanceArgField = $theseArgs[1];
                        $thisInstanceArgFieldForm = $Proj->metadata[$thisInstanceArgField]['form_name'];
                        // If this event or form/event is repeating event/instrument, the add the current instance number to arg map
                        if ( // Is a valid repeating instrument?
                            ($repeat_instrument != '' && $thisInstanceArgFieldForm == $repeat_instrument && $Proj->isRepeatingForm($thisInstanceArgEventId, $thisInstanceArgFieldForm))
                            // Is a valid repeating event?
                            || ($repeat_instrument == '' && $Proj->isRepeatingEvent($thisInstanceArgEventId) && in_array($thisInstanceArgFieldForm, $Proj->eventsForms[$thisInstanceArgEventId]))) {
                            $theseArgs[3] = $repeat_instance;
                        }
                    }
                }
                unset($theseArgs);
            }
            /*echo "<pre>";
            print_r($thisInstanceArgMap);
            echo "</pre>";*/
            foreach ($recordData as $record => &$this_record_data1) {
                $calculatedCalcVal = \LogicTester::evaluateCondition(null, $this_record_data1, $funcName, $thisInstanceArgMap, null);
                foreach (parseEnum(strip_tags(label_decode($Proj->metadata[$thisInstanceArgMap[count($thisInstanceArgMap) - 1][1]]['element_enum']))) as $this_code => $this_choice) {
                    if ($calculatedCalcVal === $this_code) {
                        $calculatedCalcVal = $this_choice;
                        break;
                    }
                }
            }
        }
        catch (\Exception $e) {
            if (strpos($e->getMessage(),"Parse error in input:") === 0 || strpos($e->getMessage(),"Unable to find next token in") === 0) {
                return $calcString;
            }
            else {
                return "";
            }
        }
        return $calculatedCalcVal;
    }

    function parseLogicString($string) {
        preg_match_all("/\[(.*?)\]/", $string, $matchRegEx);
        preg_match_all('/[+*\/-]/', $string, $matches);
        $stringsToReplace = $matchRegEx[0];
        $fieldNamesReplace = $matchRegEx[1];

        $addList = array();
        if (isset($matches[0]) && !empty($matches[0])) {
            $lastPosition = 0;
            foreach ($matches[0] as $index => $operator) {
                $thisPosition = strpos($string, $matches[0][$index],$lastPosition + 1);
                if ($index == 0) {
                    $addList[$index] = trim(substr($string,0,$thisPosition));
                } else {
                    $addList[$index] = trim(substr($string,$lastPosition + 1,($thisPosition - ($lastPosition + 1))));
                }
                $lastPosition = $thisPosition;
            }
            if ($lastPosition != "") {
                $addList[] = trim(substr($string,$lastPosition + 1));
            }
        }
        else {
            $addList[] = $string;
        }

        return array($stringsToReplace,$fieldNamesReplace,$matches[0],$addList);
    }

    /*
     * Generate the necessary Javascript code to get on-form data piping working.
     * @param $interval Initial integer.
     * @param $operator Mathematical operator to use for operation.
     * @param $operatee Second integer to be interacted on initial integer by operator
     * @return Integer result of mathematical operation.
     */
    function processOperator($interval, $operator, $operatee) {
        if (!is_numeric($interval) || !is_numeric($operatee)) {
            switch ($operator) {
                case "+":
                    return $interval.$operatee;
                    break;
                default:
                    break;
            }
        }
        else {
            switch ($operator) {
                case "+":
                    return intval($interval) + intval($operatee);
                    break;
                case "-":
                    return intval($interval) + intval($operatee);
                    break;
                case "*":
                    return intval($interval) * intval($operatee);
                    break;
                case "/":
                    return intval($interval) / intval($operatee);
                    break;
            }
        }
        return $interval.$operator.$operatee;
    }

    /*
	 * Determine the correct date formatting based on a field's element validation.
	 * @param $elementValidationType The element validation for the data field being examined.
	 * @param $type Either 'php' or 'javascript', based on where the data format string is being injected
	 * @return Date format string
	 */
    function getDateFormat($elementValidationType, $fieldName, $type) {
        $returnString = "";
        switch ($elementValidationType) {
            case "date_mdy":
                if ($type == "php") {
                    $returnString = "m-d-Y";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+'-'+$fieldName.getUTCFullYear()";
                }
                break;
            case "date_dmy":
                if ($type == "php") {
                    $returnString = "d-m-Y";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCDate())+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+$fieldName.getUTCFullYear()";
                }
                break;
            case "date_ymd":
                if ($type == "php") {
                    $returnString = "Y-m-d";
                }
                elseif ($type == "javascript") {
                    $returnString = "$fieldName.getUTCFullYear()+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())";
                }
                break;
            case "datetime_mdy":
                if ($type == "php") {
                    $returnString = "m-d-Y H:i";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+'-'+$fieldName.getUTCFullYear()+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())";
                }
                break;
            case "datetime_dmy":
                if ($type == "php") {
                    $returnString = "d-m-Y H:i";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCDate())+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+$fieldName.getUTCFullYear()+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())";
                }
                break;
            case "datetime_ymd":
                if ($type == "php") {
                    $returnString = "Y-m-d H:i";
                }
                elseif ($type == "javascript") {
                    $returnString = "$fieldName.getUTCFullYear()+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())";
                }
                break;
            case "datetime_seconds_mdy":
                if ($type == "php") {
                    $returnString = "m-d-Y H:i:s";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+'-'+$fieldName.getUTCFullYear()+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())+':'+addZ($fieldName.getUTCSeconds())";
                }
                break;
            case "datetime_seconds_dmy":
                if ($type == "php") {
                    $returnString = "d-m-Y H:i:s";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCDate())+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+$fieldName.getUTCFullYear()+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())+':'+addZ($fieldName.getUTCSeconds())";
                }
                break;
            case "datetime_seconds_ymd":
                if ($type == "php") {
                    $returnString = "Y-m-d H:i:s";
                }
                elseif ($type == "javascript") {
                    $returnString = "$fieldName.getUTCFullYear()+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())+':'+addZ($fieldName.getUTCSeconds())";
                }
                break;
            default:
                $returnString = '';
        }
        return $returnString;
    }

    function generateSurveyLink(\Project $project, $formName, $record, $event_id, $repeat_instance, $instanceMatch, $recordData) {
        $surveyLink = "";
        $maxInstance = $repeat_instance;

        if (property_exists($project,'forms') && in_array($formName,array_keys($project->forms))) {
            if ($project->isRepeatingForm($event_id,$formName)) {
                if ($instanceMatch != "") {
                    $saveList[$record]['repeat_instances'][$event_id][$formName][$maxInstance][$formName."_complete"] = 0;
                }
                else {
                    $maxInstance = (max(array_keys($recordData[$record]['repeat_instances'][$event_id][$formName])) ? max(array_keys($recordData[$record]['repeat_instances'][$event_id][$formName])) : 1);
                    $saveList[$record]['repeat_instances'][$event_id][$formName][$maxInstance][$formName."_complete"] = 0;
                }
            }
            else {
                $saveList[$record][$event_id][$formName."_complete"] = 0;
            }
            if (!empty($saveList)) {
                /*$changes[$recordID]['redcap_repeat_instance'] = $instance;
                $changes[$recordID]['redcap_repeat_instrument'] = $notifForm;
                $changes[$recordID][$this->getProjectSetting('unique-user')] = $user;*/
                $result = \REDCap::saveData($project->project_id, 'array', $saveList, 'overwrite');
                if (!empty($result['errors']) && $result['errors'] != "") {
                    $errorString = "";
                    foreach ($result['errors'] as $error) {
                        $errorString .= $error."<br/>";
                    }
                    throw new \Exception($errorString);
                }
                else {
                    $surveyLink = \REDCap::getSurveyLink($record,$formName,$event_id,$maxInstance,$project->project_id);
                }
            }
        }
        //ExternalModules::exitAfterHook();
        return $surveyLink;
    }
}