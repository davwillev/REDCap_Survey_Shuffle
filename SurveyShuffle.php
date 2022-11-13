<?php

namespace INTERSECT\SurveyShuffle;

use ExternalModules\AbstractExternalModule;
use Project;
use REDCap;

class SurveyShuffle extends \ExternalModules\AbstractExternalModule {

    function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    /* function redcap_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) // For debugging */
    {
        //Retrieve module configuration settings
        $shuffle_instruments = $this -> getProjectSetting('shuffle-instruments');
        $shuffle_number = $this -> getProjectSetting('shuffle-number');
        $exit_survey = $this -> getProjectSetting('exit-survey');
        $prior_survey = $this -> getProjectSetting('prior-survey');
        $sequence_field = $this -> getProjectSetting('sequence-field');
        $record_id_field = REDCap::getRecordIdField();

        // Set the number of instruments to shuffle to the number requested, or else the number of instruments being shuffled
        $shuffle_number = (!is_null($shuffle_number) && is_numeric($shuffle_number) && $shuffle_number > 0) ? $shuffle_number : count($shuffle_instruments);

        // Since this module fires on the survey_complete hook, we have to know if the survey that was just completed was one that should then go to a random next one. Those are: the list of shuffled surveys, the prior survey is it is set, or the first survey if it is not.
        $trigger_instruments = $shuffle_instruments;
        $first_instrument = array_key_first(REDCap::getInstrumentNames());
        (is_null($prior_survey)) ? array_push($trigger_instruments,$first_instrument) : array_push($trigger_instruments,$prior_survey);

        if (in_array($instrument,$trigger_instruments)) { // Stop if the current instrument should *not* lead to a shuffled instrument
           
            $completed_shuffle_instruments = array(); // Instantiate an array for completed instruments

            // Loop through shuffle instruments and remove those that are complete, adding them to the completed instruments array
            foreach($shuffle_instruments as $inst) {
                if (REDCap::getData('array',$record,$inst.'_complete',$event_id)[$record][$event_id][$inst.'_complete'] == 2) {
                    unset($shuffle_instruments[array_search($inst,$shuffle_instruments)]);
                    array_push($completed_shuffle_instruments, $inst);
                };
            }
            if (count($completed_shuffle_instruments) < $shuffle_number) { // If we're not done displaying surveys
                shuffle($shuffle_instruments);
                $next_survey = $shuffle_instruments[0]; // Pick a remaining instrument at random
                if (!is_null($sequence_field)) { // If we're planning on storing the sequence in a field, let's do so.
                    $sequence_data_curr = REDCap::getData('array',$record,$sequence_field,$event_id)[$record][$event_id][$sequence_field];
                    $params = array('dataFormat'=>'json', 'type'=>'flat', 'data'=>'[{"'.$record_id_field.'":"'.$record.'","'.$sequence_field.'":"'.$sequence_data_curr.$next_survey.', "}]');
                    REDCap::saveData($params);
                };
                $next_survey_link = REDCap::getSurveyLink($record, $next_survey); // Get the next survey's link
                header('Location: '.$next_survey_link); // And direct the respondent there
                exit();
            } else { // If we're done
                if (!is_null($exit_survey)) { // Test if there's a configured end-survey
                    $exit_survey_link = REDCap::getSurveyLink($record, $exit_survey);
                    header('Location: '.$exit_survey_link); // Go there
                    exit();
                };
            }; // Otherwise do whatever the survey termination options have configured.
        };
    }
}
