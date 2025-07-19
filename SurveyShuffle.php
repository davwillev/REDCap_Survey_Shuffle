<?php
/**
 * Shuffles Surveys; Displays selected surveys to respondents in a random order.
 * Order can be random, or predetermined by a field that stores an instrument sequence.
 *
 * @author Aidan Wilson <aidan.wilson@intersect.org.au>
 * @link https://github.com/jangari/redcap_survey_shuffle
 */

namespace INTERSECT\SurveyShuffle;

use ExternalModules\AbstractExternalModule;
use REDCap;

class SurveyShuffle extends \ExternalModules\AbstractExternalModule {

    function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    /* function redcap_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) // For debugging */
    {
        $configs = $this -> getProjectSetting('configs');

        for ($i = 0; $i < count($configs); $i++){ // Loop over each new configuration
            //Retrieve module configuration settings
            $shuffle_event = $this -> getProjectSetting('shuffle-event')[$i];
            $shuffle_type = $this -> getProjectSetting('shuffle-type')[$i];
            $entry_survey = $this -> getProjectSetting('entry-survey')[$i];
            $descriptive_text = $this -> getProjectSetting('descriptive-text')[$i];
            $shuffle_instruments = $this -> getProjectSetting('shuffle-instruments')[$i];
            $order_field = $this -> getProjectSetting('order-field')[$i];
            $order_field_event = $this -> getProjectSetting('order-field-event')[$i];
            $shuffle_number = $this -> getProjectSetting('shuffle-number')[$i];
            $exit_survey = $this -> getProjectSetting('exit-survey')[$i];
            $sequence_field = $this -> getProjectSetting('sequence-field')[$i];
            $sequence_field_event = $this -> getProjectSetting('sequence-field-event')[$i];

            // Exit if entry survey is not set, or if no shuffle instruments are defined
            if (empty($entry_survey) || empty($shuffle_instruments)) {
                return;
            }

            if (is_null($shuffle_event) || $event_id == $shuffle_event ) { // proceed if the current event is the config event, or if no event is specified
                if (is_null($shuffle_type) || $shuffle_type == "random") { // proceed if the shuffle type is set to shuffle, or if no type is specified (for backwards compatibility)

                    // Set the number of instruments to shuffle to the number requested, or else the number of instruments being shuffled
                    $shuffle_number = (!is_null($shuffle_number) && is_numeric($shuffle_number) && $shuffle_number > 0) ? $shuffle_number : count($shuffle_instruments);

                    // Since this module fires on the survey_complete hook, we have to know if the survey that was just completed was one that should then go to a random next one. Those are: the list of shuffled surveys, the entry survey is it is set, or the first survey if it is not.
                    $trigger_instruments = $shuffle_instruments;
                    $first_instrument = array_key_first(REDCap::getInstrumentNames());
                    (is_null($entry_survey)) ? array_push($trigger_instruments,$first_instrument) : array_push($trigger_instruments,$entry_survey);

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
                                $sequence_field_event = $sequence_field_event ?? $event_id; // If no event is specified for sequence_field_event, use the current event
                                $sequence_data_curr = REDCap::getData('array',$record,$sequence_field,$sequence_field_event)[$record][$sequence_field_event][$sequence_field];
                                $sequence_new_data = (strlen($sequence_data_curr) == 0) ? $next_survey : $sequence_data_curr.", ".$next_survey;
                                $sequence_data = [
                                    $record => [
                                        $sequence_field_event => [
                                            $sequence_field => $sequence_new_data
                                        ]
                                    ]
                                ];
                                REDCap::saveData('array', $sequence_data);
                            };
                            $next_survey_link = REDCap::getSurveyLink($record, $next_survey, $event_id); // Get the next survey's link
                            header('Location: '.$next_survey_link); // And direct the respondent there
                            $this->exitAfterHook();
                        } else { // If we're done
                            if (!is_null($exit_survey)) { // Test if there's a configured end-survey
                                $exit_survey_link = REDCap::getSurveyLink($record, $exit_survey, $event_id);
                                header('Location: '.$exit_survey_link); // Go there
                                $this->exitAfterHook();
                            };
                        }; // Otherwise do whatever the survey termination options have configured.
                    };
                } else if ($shuffle_type == "field") { // If the shuffle type is set to a predetermined order from a field
                    $order_field_event = $order_field_event ?? $event_id; // If no event is specified for sequence_field_event, use the current event
                    // User REDCap::getData to get the order field's value
                    $order_field_data = REDCap::getData('array',$record,$order_field,$order_field_event)[$record][$order_field_event][$order_field];
                    // Split this by ", " to get an ordered array of instruments
                    $order_field_data = explode(", ",$order_field_data);
                    // If the current instrument is the entry survey, then set the redirect to the first instrument in the order field
                    if ($instrument == $entry_survey) {
                        $next_survey = $order_field_data[0];
                        $next_survey_link = REDCap::getSurveyLink($record, $next_survey, $event_id); // Get the next survey's link
                        header('Location: '.$next_survey_link); // And direct the respondent there
                        $this->exitAfterHook();
                    } else if ($instrument != $order_field_data[count($order_field_data)-1]) { // If the current instrument is not the last instrument in the order field, then set the redirect to the next instrument in the order field
                        $next_survey = $order_field_data[array_search($instrument,$order_field_data)+1];
                        $next_survey_link = REDCap::getSurveyLink($record, $next_survey, $event_id); // Get the next survey's link
                        header('Location: '.$next_survey_link); // And direct the respondent there
                        $this->exitAfterHook();
                    } else if (!is_null($exit_survey)) { // If the current instrument is the last instrument in the order field and there is an exit survey configured, then set the redirect to the exit survey
                        $next_survey = $exit_survey;
                        $next_survey_link = REDCap::getSurveyLink($record, $next_survey, $event_id); // Get the next survey's link
                        header('Location: '.$next_survey_link); // And direct the respondent there
                        $this->exitAfterHook();
                    }; // Otherwise if no exist survey is configured and the sequence has been exhausted, do whatever the survey termination options have configured.
                };
            }
        }
    }
}
