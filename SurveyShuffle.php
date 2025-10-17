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
        // Evaluate project context (surveys/forms/both)
        $context = $this->getProjectSetting('shuffle-context');
        if ($context != 'survey' && $context != 'both') {
            return;
        }

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
                //return;
                continue;
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

    /**
     * Runs when a data entry form is opened (top of form).
     * Event-specific filtering and sequence storage mirror survey behaviour.
     */
    function redcap_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {
        $context = $this->getProjectSetting('shuffle-context');
        if ($context != 'form' && $context != 'both') {
            return;
        }

        echo "<!-- SurveyShuffle debug: context={$context}, instrument={$instrument}, event={$event_id} -->";

        $configs = $this->getProjectSetting('configs');

        for ($i = 0; $i < count($configs); $i++) {
            // Settings for this configuration
            $shuffle_event         = $this->getProjectSetting('shuffle-event')[$i];
            $shuffle_type          = $this->getProjectSetting('shuffle-type')[$i];
            $entry_survey          = $this->getProjectSetting('entry-survey')[$i]; // entry form
            $shuffle_instruments   = $this->getProjectSetting('shuffle-instruments')[$i];
            $sequence_field        = $this->getProjectSetting('sequence-field')[$i];
            $sequence_field_event  = $this->getProjectSetting('sequence-field-event')[$i];
            $order_field           = $this->getProjectSetting('order-field')[$i];
            $order_field_event     = $this->getProjectSetting('order-field-event')[$i];
            $shuffle_number        = $this->getProjectSetting('shuffle-number')[$i];

            // Skip configs without entry form or shuffle list
            if (empty($entry_survey) || empty($shuffle_instruments)) continue;

            // Event filter (match or run everywhere if not set)
            if (!is_null($shuffle_event) && $shuffle_event != $event_id) continue;

            echo "<!-- Config {$i}: entry={$entry_survey}, shuffle_instruments=" . implode(',', (array)$shuffle_instruments) . " -->";

            // Prepare array of instruments for this form
            $shuffle_array = [];

            // Entry form check
            if ($entry_survey == $instrument) {

                // ENTRY FORM: generate and store shuffled sequence
                if (is_null($shuffle_type) || $shuffle_type == 'random') {
                    $shuffle_array = $shuffle_instruments;
                    shuffle($shuffle_array);

                    // Apply limit if requested (keep behaviour analogous to surveys)
                    if (!is_null($shuffle_number) && is_numeric($shuffle_number) && $shuffle_number > 0 && $shuffle_number < count($shuffle_instruments)) {
                        $shuffle_array = array_slice($shuffle_array, 0, $shuffle_number);
                    }

                    // Save shuffled order if requested
                    if (!is_null($sequence_field)) {
                        $sequence_event = $sequence_field_event ?? $event_id;
                        $sequence_value = implode(", ", $shuffle_array);
                        REDCap::saveData('array', [
                            $record => [
                                $sequence_event => [
                                    $sequence_field => $sequence_value
                                ]
                            ]
                        ]);
                    }

                } else if ($shuffle_type == 'field') {
                    if (!empty($order_field)) {
                        $sequence_event = $order_field_event ?? $event_id;
                        $data = REDCap::getData('array', $record, $order_field, $sequence_event);
                        $order_value = $data[$record][$sequence_event][$order_field] ?? '';
                        if (strlen($order_value)) {
                            // Match survey delimiter behaviour
                            $shuffle_array = explode(", ", $order_value);
                        }
                    }
                }

            } else {

                // NON-ENTRY FORMS: retrieve stored sequence (previously saved)
                if (!empty($sequence_field)) {
                    $sequence_event = $sequence_field_event ?? $event_id;
                    $data = REDCap::getData('array', $record, $sequence_field, $sequence_event);
                    $sequence_value = $data[$record][$sequence_event][$sequence_field] ?? '';
                    if (strlen($sequence_value)) {
                        // Match survey delimiter behaviour
                        $shuffle_array = explode(", ", $sequence_value);
                    }
                }
            }

            // Navigation button logic (applies to all shuffled forms)
            if (!empty($shuffle_array)) {
                // Normalise once
                $clean_array = array_map('trim', $shuffle_array);

                // Helper: next native form after a given form (project order)
                $native_order = array_keys(REDCap::getInstrumentNames());

                $next_form = null;

                if ($instrument === $entry_survey) {
                    // Entry form -> first in shuffled list
                    $next_form = $clean_array[0] ?? null;
                } else {
                    echo "<!-- Debug: instrument='{$instrument}', shuffle_array=" . json_encode($clean_array) . " -->";
                    $idx = array_search($instrument, $clean_array, true);

                    if ($idx !== false) {
                        if (isset($clean_array[$idx + 1])) {
                            // Middle of block -> next in block
                            $next_form = $clean_array[$idx + 1];
                        } else {
                            // Compute native boundary of the block (max native index across all shuffled instruments)
                            $native_index = array_flip($native_order);

                            $max_idx = -1;
                            foreach ($clean_array as $f) {
                                if (isset($native_index[$f])) {
                                    $max_idx = max($max_idx, $native_index[$f]);
                                }
                            }
                            $next_form = ($max_idx >= 0 && isset($native_order[$max_idx + 1]))
                                ? $native_order[$max_idx + 1]
                                : null; // no native form after the block (block ends at last instrument)
                        }
                    }
                    // Optional: steer users into the block if they land outside it.
                }

                // If a next form exists, render our own "Go to shuffled form" button
                if (!empty($next_form)) {

                    $next_url = APP_PATH_WEBROOT . "DataEntry/index.php?pid={$project_id}&page={$next_form}&id={$record}&event_id={$event_id}";

                    // Diagnostics (unobtrusive, visible in page source)
                    $seqComment = implode(' → ', array_map('trim', $shuffle_array));
                    $seqLen     = count($shuffle_array);
                    $cfgIdx     = $i;

                    echo "<!-- SurveyShuffle DIAG: cfg={$cfgIdx}, instrument={$instrument}, event={$event_id}, entry={$entry_survey}, next={$next_form}, seqlen={$seqLen} -->\n";
                    echo "<!-- SurveyShuffle SEQ: {$seqComment} -->\n";
                    echo "<!-- Shuffle sequence for {$record}: {$seqComment} -->\n";

                    echo "
                    <script>
                    (function() {
                        if (window.__SS_HIJACKED__) return;
                        window.__SS_HIJACKED__ = true;

                        var HIJACK_DELAY_MS = 400;
                        var REDIRECT_DELAY_MS = 800;

                        function doSaveStay(\$originBtn) {
                            // Try the native function first
                            if (typeof dataEntrySubmit === 'function') {
                                dataEntrySubmit('submit-btn-savecontinue');
                                return true; // we attempted a real Save & Stay
                            }
                            // Otherwise try clicking a different Save & Stay element than the one we're binding to
                            var \$saveStayOther = $(\"#submit-btn-savecontinue, [name='submit-btn-savecontinue']\").not(\$originBtn).first();
                            if (\$saveStayOther.length) {
                                \$saveStayOther.trigger('click');
                                return true; // we attempted a Save & Stay via DOM
                            }
                            // No Save & Stay available -> signal failure to caller
                            return false;
                        }

                        function bindHandler(\$btn) {
                            // Remove inline onclick and any prior SS handler, then add ours
                            \$btn.attr('onclick','');
                            \$btn.off('click.surveyshuffle').on('click.surveyshuffle', function(e){
                                e.preventDefault();

                                // Only proceed if Save & Stay is callable
                                var saved = doSaveStay(\$btn);
                                if (!saved) {
                                    alert('Save & Stay is not available on this form, so your data cannot be saved safely. Please contact the project administrator.');
                                    return; // DO NOT navigate
                                }

                                // Otherwise, continue with your existing delayed redirect
                                setTimeout(function(){ window.location.href = " . json_encode($next_url) . "; }, REDIRECT_DELAY_MS);
                            });
                        }

                        setTimeout(function() {
                            var \$nextFormBtn = $(\"[name='submit-btn-savenextform']\");

                            if (!\$nextFormBtn.length) {
                                var \$container = $(\"#__SUBMITBUTTONS__-div\");
                                if (\$container.length && $(\"#surveyshuffle-nextform\").length === 0) {
                                    var \$saveStay = $(\"#submit-btn-savecontinue, [name='submit-btn-savecontinue']\").first();
                                    var html = '<button type=\"button\" class=\"btn btn-primaryrc\" ' +
                                            'id=\"surveyshuffle-nextform\" ' +
                                            'style=\"margin-left:6px;margin-bottom:2px;font-size:13px !important;padding:6px 8px;\" ' +
                                            'aria-label=\"Save and go to next form (shuffled)\">' +
                                            '<span>Save &amp; Go To Next Form</span></button>';
                                    if (\$saveStay.length) { \$saveStay.after(html); } else { \$container.append(html); }
                                }
                                \$nextFormBtn = $(\"#surveyshuffle-nextform\");
                            }

                            if (\$nextFormBtn.length) {
                                bindHandler(\$nextFormBtn);
                            } else {
                                // Fallback: bind our behaviour to Save & Stay itself (but avoid recursion via doSaveStay)
                                var \$saveStayBtn = $(\"#submit-btn-savecontinue, [name='submit-btn-savecontinue']\").first();
                                if (\$saveStayBtn.length) bindHandler(\$saveStayBtn);
                            }
                        }, HIJACK_DELAY_MS);
                    })();
                    </script>
                    ";
                    } else {
                    // Sequence exhausted: leave page behaviour unchanged (optional note)
                    echo "<!-- SurveyShuffle DIAG: no-next-form (end-of-sequence) for record {$record} on {$instrument} -->\n";
                }
            }
        }
    }
}
