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

    /**
     * Runs when a survey is completed.
     * Execution is limited according to the selected shuffle context (surveys/forms/both).
     */
    function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        // Retrieve shuffle context setting
        $context = $this->getProjectSetting('shuffle-context');
        if ($context != 'survey' && $context != 'both') {
            return;
        }

        $configs = $this->getProjectSetting('configs');

        for ($i = 0; $i < count($configs); $i++) {
            // Retrieve module configuration settings
            $shuffle_event = $this->getProjectSetting('shuffle-event')[$i];
            $shuffle_type = $this->getProjectSetting('shuffle-type')[$i];
            $entry_survey = $this->getProjectSetting('entry-survey')[$i];
            $descriptive_text = $this->getProjectSetting('descriptive-text')[$i];
            $shuffle_instruments = $this->getProjectSetting('shuffle-instruments')[$i];
            $order_field = $this->getProjectSetting('order-field')[$i];
            $order_field_event = $this->getProjectSetting('order-field-event')[$i];
            $shuffle_number = $this->getProjectSetting('shuffle-number')[$i];
            $exit_survey = $this->getProjectSetting('exit-survey')[$i];
            $sequence_field = $this->getProjectSetting('sequence-field')[$i];
            $sequence_field_event = $this->getProjectSetting('sequence-field-event')[$i];

            // Exit if entry survey or shuffle instruments are not defined
            if (empty($entry_survey) || empty($shuffle_instruments)) continue;

            // Proceed only when the current survey matches the configured entry point
            if ($entry_survey == $instrument) {

                // Randomised shuffle
                if ($shuffle_type == 'random') {
                    $shuffle_array = $shuffle_instruments;
                    shuffle($shuffle_array);
                    if (!empty($shuffle_number) && is_numeric($shuffle_number) && $shuffle_number > 0 && $shuffle_number < count($shuffle_instruments)) {
                        $shuffle_array = array_slice($shuffle_array, 0, $shuffle_number);
                    }

                    // Store sequence if a field is defined
                    if (!empty($sequence_field)) {
                        $sequence_event = (!empty($sequence_field_event)) ? $sequence_field_event : $event_id;
                        $sequence_value = implode(",", $shuffle_array);
                        REDCap::saveData($project_id, 'array', [
                            $record => [
                                $sequence_event => [
                                    $sequence_field => $sequence_value
                                ]
                            ]
                        ]);
                    }

                    // Redirect to the first shuffled survey
                    $next_survey = $shuffle_array[0];
                    $this->redirectToSurvey($project_id, $record, $next_survey, $event_id);

                // Predefined sequence from field
                } elseif ($shuffle_type == 'field') {
                    if (!empty($order_field)) {
                        $sequence_event = (!empty($order_field_event)) ? $order_field_event : $event_id;
                        $data = REDCap::getData($project_id, 'array', $record, $order_field, $sequence_event);
                        $order_value = reset($data)[$sequence_event][$order_field] ?? null;

                        if (!empty($order_value)) {
                            $order_array = explode(',', $order_value);
                            $next_survey = reset($order_array);
                            $this->redirectToSurvey($project_id, $record, $next_survey, $event_id);
                        }
                    }
                }
            }
        }
    }

    /**
     * Runs when a data entry form is opened (top of form).
     * Now supports event-specific form shuffling (longitudinal projects).
     */
    function redcap_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {
        $context = $this->getProjectSetting('shuffle-context');
        if ($context != 'form' && $context != 'both') {
            return;
        }

        $configs = $this->getProjectSetting('configs');

        for ($i = 0; $i < count($configs); $i++) {
            // Get settings for this configuration
            $shuffle_event = $this->getProjectSetting('shuffle-event')[$i];
            $shuffle_type = $this->getProjectSetting('shuffle-type')[$i];
            $entry_survey = $this->getProjectSetting('entry-survey')[$i]; // entry form
            $shuffle_instruments = $this->getProjectSetting('shuffle-instruments')[$i];
            $sequence_field = $this->getProjectSetting('sequence-field')[$i];
            $sequence_field_event = $this->getProjectSetting('sequence-field-event')[$i];
            $order_field = $this->getProjectSetting('order-field')[$i];
            $order_field_event = $this->getProjectSetting('order-field-event')[$i];
            $shuffle_number = $this->getProjectSetting('shuffle-number')[$i];

            // Skip configs without entry form or shuffle list
            if (empty($entry_survey) || empty($shuffle_instruments)) continue;

            // Event filtering (only run for matching event or if none specified) ---
            if (!empty($shuffle_event) && $shuffle_event != $event_id) continue;

            // Check if current instrument is the designated entry form
            if ($entry_survey == $instrument) {

                $shuffle_array = [];

                if ($shuffle_type == 'random') {
                    $shuffle_array = $shuffle_instruments;
                    shuffle($shuffle_array);

                    // Trim if subset requested
                    if (!empty($shuffle_number) && is_numeric($shuffle_number) && $shuffle_number > 0 && $shuffle_number < count($shuffle_instruments)) {
                        $shuffle_array = array_slice($shuffle_array, 0, $shuffle_number);
                    }

                    // Save shuffled order if requested
                    if (!empty($sequence_field)) {
                        $sequence_event = (!empty($sequence_field_event)) ? $sequence_field_event : $event_id;
                        $sequence_value = implode(",", $shuffle_array);
                        REDCap::saveData($project_id, 'array', [
                            $record => [
                                $sequence_event => [
                                    $sequence_field => $sequence_value
                                ]
                            ]
                        ]);
                    }

                } elseif ($shuffle_type == 'field') {
                    if (!empty($order_field)) {
                        $sequence_event = (!empty($order_field_event)) ? $order_field_event : $event_id;
                        $data = REDCap::getData($project_id, 'array', $record, $order_field, $sequence_event);
                        $order_value = reset($data)[$sequence_event][$order_field] ?? null;
                        if (!empty($order_value)) {
                            $shuffle_array = explode(',', $order_value);
                        }
                    }
                }

                // --- Override "Save & Go to Next Form" button to follow shuffled order ---
                if (!empty($shuffle_array)) {
                    $current_index = array_search($instrument, $shuffle_array);
                    if ($current_index !== false && isset($shuffle_array[$current_index + 1])) {
                        $next_form = $shuffle_array[$current_index + 1];
                        $next_url = REDCap::getDataEntryUrl($project_id, $record, $next_form, $event_id);

                        echo "
                        <script>
                        $(function(){
                            var btn = $(\"button[name='submit-btn-saverecord_nextform']\");
                            if(btn.length){
                                btn.off('click').on('click', function(e){
                                    e.preventDefault();
                                    // Submit form normally
                                    $('form#form').submit();
                                    // Redirect to next form after short delay
                                    setTimeout(function(){
                                        window.location.href = '{$next_url}';
                                    }, 500);
                                });
                            }
                        });
                        </script>
                        ";
                    }
                }
            }
        }
    }

    /**
     * Redirects the participant to the specified survey.
     */
    private function redirectToSurvey($project_id, $record, $survey, $event_id)
    {
        $survey_id = REDCap::getSurveyId($survey);
        if (empty($survey_id)) return;

        $survey_hash = REDCap::getSurveyHash($project_id, $survey_id, $event_id);
        if (empty($survey_hash)) return;

        $url = REDCap::getSurveyLink($record, $survey_id, $event_id);
        if (!empty($url)) {
            header("Location: $url");
            exit;
        }
    }
}
