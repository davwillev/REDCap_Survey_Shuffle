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

            // Event filter (match or run everywhere if not set)
            if (!is_null($shuffle_event) && $shuffle_event != $event_id) continue;

            echo "<!-- Config {$i}: entry={$entry_survey}, shuffle_instruments=" . implode(',', (array)$shuffle_instruments) . " -->";

            // Prepare array of instruments for this form
            $shuffle_array = [];

            // Entry form check
            if ($entry_survey == $instrument) {

                // ENTRY FORM: generate and store shuffled sequence
                    if (is_null($shuffle_type) || $shuffle_type == 'random') {
                        // If a sequence field is configured, reuse it if already set to avoid re-shuffling on Save & Stay
                        $sequence_event = $sequence_field_event ?? $event_id;
                        $existing_value = '';
                        if (!empty($sequence_field)) {
                            $existing = REDCap::getData('array', $record, $sequence_field, $sequence_event);
                            $existing_value = $existing[$record][$sequence_event][$sequence_field] ?? '';
                        }

                        if (strlen(trim($existing_value))) {
                            // Reuse stored sequence
                            $shuffle_array = array_map('trim', explode(',', $existing_value));
                        } else {
                            // First time only: create sequence
                            $shuffle_array = $shuffle_instruments;
                            shuffle($shuffle_array);

                            // Apply limit if requested (keep behaviour analogous to surveys)
                            if (!is_null($shuffle_number) && is_numeric($shuffle_number) && $shuffle_number > 0 && $shuffle_number < count($shuffle_instruments)) {
                                $shuffle_array = array_slice($shuffle_array, 0, $shuffle_number);
                            }

                            // Save shuffled order if requested
                            if (!empty($sequence_field)) {
                                REDCap::saveData('array', [
                                    $record => [
                                        $sequence_event => [
                                            $sequence_field => implode(", ", $shuffle_array)
                                        ]
                                    ]
                                ]);
                            }
                        }
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
                } elseif ($shuffle_type === 'field' && !empty($order_field)) {
                    // Fallback for 'From field' when sequence_field is not used
                    $sequence_event = $order_field_event ?? $event_id;
                    $data = REDCap::getData('array', $record, $order_field, $sequence_event);
                    $order_value = $data[$record][$sequence_event][$order_field] ?? '';
                    if (strlen(trim($order_value))) {
                        $shuffle_array = array_map('trim', explode(',', $order_value));  
                    }
                }
            }

            // Use the order list for scope when we're in "From field" mode; otherwise use shuffle-instruments.
            $useOrderList = ($shuffle_type === 'field' && !empty($shuffle_array));
            $scopeList = array_map('trim', $useOrderList ? $shuffle_array : (array)$shuffle_instruments);

            // Gate: only the entry form or a form named in the scope list is affected.
            if ($instrument !== $entry_survey && !in_array($instrument, $scopeList, true)) {
                continue; // stop before any next-form calculations or JS injection
            }

            if (!empty($shuffle_array)) {
                $next_form = null;

                // If we are on the entry form, the next is the FIRST in the shuffled list
                if ($instrument === $entry_survey) {
                    $next_form = $shuffle_array[0] ?? null;
                } else {
                    // Otherwise, progress to the next item in the shuffled list
                    echo "<!-- Debug: instrument='{$instrument}', shuffle_array=" . json_encode($shuffle_array) . " -->";
                    //$idx = array_search($instrument, $shuffle_array, true);
                    // Normalise whitespace to prevent mismatched entries (e.g. " demographics" vs "demographics")
                    $clean_array = array_map('trim', $shuffle_array);
                    $idx = array_search($instrument, $clean_array);
                    if ($idx !== false && isset($shuffle_array[$idx + 1])) {
                        $next_form = $shuffle_array[$idx + 1];
                    }
                }

                if (!empty($next_form)) {

                    // Define the next URL outside of the JS block for cleaner access
                    $next_url = APP_PATH_WEBROOT . "DataEntry/index.php?pid={$project_id}&page={$next_form}&id={$record}&event_id={$event_id}";

                    echo "
                    <script>

                    // Use jQuery document ready function
                    $(function() {
                        // We define these variables outside the click handler to maintain their state
                        var nextFormUrl   = '{$next_url}';
                        var saveInProcess = false;
                        var ajaxStarted   = false; // NEW: track whether a save AJAX actually started

                        // Bind after REDCap has wired its own handlers
                        $(window).on('load', function() {
                            // Scope to this form's submit area only
                            var area = $(\"#__SUBMITBUTTONS__-div\");
                            if (area.data('shuffleWired')) return;  // idempotency guard
                            area.data('shuffleWired', true);

                            // If no next form (last in shuffled sequence), hide native Next and exit
                            if (!nextFormUrl || !String(nextFormUrl).trim().length) {
                                var nativeNext = area.find(\"[name='submit-btn-savenextform'], #submit-btn-savenextform, a#submit-btn-savenextform, a[name='submit-btn-savenextform']\");
                                nativeNext.each(function(){
                                    var el = $(this);
                                    el.hide();                // hide the control itself
                                    el.closest('li').hide();  // hide dropdown <li> if applicable
                                });
                                return; // do not inject or hijack anything on the last shuffled form
                            }
                            
                            // Try native button first, then the dropdown <a>
                            var btn  = area.find(\"[name='submit-btn-savenextform']\");
                            if (!btn.length) {
                                var btnLink = area.find(\"a#submit-btn-savenextform, a[name='submit-btn-savenextform']\");
                                if (btnLink.length) btn = btnLink;
                            }

                            // If neither exists, inject our own button
                            if (!btn.length) {
                                var container = area.find('.btn-group.nowrap');
                                if (!container.length) container = area;

                                if (!$('#submit-btn-shuffled-nextform').length) {
                                    var injected = $('<button class=\"btn btn-primaryrc\" ' +
                                                    'id=\"submit-btn-shuffled-nextform\" ' +
                                                    'type=\"button\" ' +
                                                    'style=\"margin-bottom:2px;font-size:13px !important;padding:6px 8px;\">' +
                                                    '<span>Save & Go To Next Form</span></button>');
                                    container.prepend(injected);
                                    btn = injected;
                                }
                            }

                            if (!btn.length) return;

                            // Hide any duplicate dropdown 'Next' item to avoid two different Next targets
                            var dupDropdownNext = area.find(\"a#submit-btn-savenextform, a[name='submit-btn-savenextform']\");
                            if (dupDropdownNext.length && !btn.is(dupDropdownNext)) {
                                dupDropdownNext.hide().closest('li').hide();
                            }

                            // Remove inline onclick that calls dataEntrySubmit(this)
                            btn.attr('onclick','');

                            // Remove any jQuery handlers, then add our own
                            btn.off('click').on('click', function(e){
                                e.preventDefault();
                                saveInProcess = true; // Flag that our save is starting
                                ajaxStarted   = false; // reset before wiring listeners

                                // Detect if the save AJAX actually starts
                                $(document).one('ajaxSend.SurveyShuffleNext', function(event, jqxhr, settings) {
                                    var isPost        = settings.type && settings.type.toUpperCase() === 'POST';
                                    var hitsDataEntry = /\\/DataEntry\\/index\\.php/i.test(settings.url);

                                    // Determine current pid
                                    var currPid = (typeof pid !== 'undefined') ? String(pid) : (function(){
                                        var m = (window.location.search || '').match(/[?&]pid=(\\d+)/);
                                        return m ? m[1] : '';
                                    })();
                                    var hitsThisPid = currPid ? (settings.url.indexOf('pid=' + currPid) !== -1) : true;

                                    if (isPost && hitsDataEntry && hitsThisPid) {
                                        ajaxStarted = true;
                                    }
                                });

                                // 1. Set up the AJAX listener (must be done before triggering the save)
                                // Monitors ALL completed AJAX requests on the page (namespaced + one-time to avoid conflicts)
                                $(document).one('ajaxComplete.SurveyShuffleNext', function(event, xhr, settings) {
                                    // Check if this is the form save URL and that our custom save process is active
                                    var isPost        = settings.type && settings.type.toUpperCase() === 'POST';
                                    var hitsDataEntry = /\\/DataEntry\\/index\\.php/i.test(settings.url);

                                    // Determine current pid
                                    var currPid = (typeof pid !== 'undefined') ? String(pid) : (function(){
                                        var m = (window.location.search || '').match(/[?&]pid=(\\d+)/);
                                        return m ? m[1] : '';
                                    })();
                                    var hitsThisPid = currPid ? (settings.url.indexOf('pid=' + currPid) !== -1) : true;

                                    // Ensure it was a success by checking the response status
                                    if (saveInProcess && isPost && hitsDataEntry && hitsThisPid && xhr.status === 200) {
                                        // 3. SAVE COMPLETE: Redirect the user
                                        saveInProcess = false; // Reset flag
                                        window.location.href = nextFormUrl;
                                    }
                                });

                                // 2. Trigger the native save function (start the AJAX save process)
                                // Prefer REDCap's Save & Stay (avoid Save & Exit unless necessary)
                                var saveStayBtn  = area.find(\"button[name='submit-btn-savecontinue']\");
                                var saveStayLink = area.find(\"a#submit-btn-savecontinue\"); // some builds use an <a> in the dropdown

                                if (saveStayBtn.length) {
                                    saveStayBtn.trigger('click');
                                } else if (saveStayLink.length) {
                                    saveStayLink.trigger('click');
                                } else {
                                    // Last resort
                                    area.find(\"button[name='submit-btn-saverecord']\").trigger('click');
                                }

                                // Optional safety fallback if ajaxComplete never fires (older builds)
                                setTimeout(function () {
                                    // Only redirect if a save AJAX actually started (prevents redirect on validation failure)
                                    if (saveInProcess && ajaxStarted) {
                                        saveInProcess = false;
                                        window.location.href = nextFormUrl;
                                    }
                                }, 2500);

                                // The redirect logic is handled by the ajaxComplete listener.
                            });
                        });
                    });

                    </script>";
                }
            }              
        }
    }
}
