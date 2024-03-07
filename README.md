# REDCap Survey Shuffle

This REDCap External Module allows users to shuffle surveys such that they are displayed to respondents in a random order, and the order in which they were displayed can be stored in a text variable.

## Installation

### From Module Repository

Install the module from the REDCap module repository and enable in the Control Center, then enable on projects as needed.

### From GitHub

Clone the repository and rename the directory to include a version number, e.g., `survey_shuffle_v1.0.0`, and copy to your modules directory, then enable in Control Center and on projects as needed.

## Usage

### Shuffle Type

This module allows for two types of survey shuffling: randomised and From Field, for retrieving a predetermined sequence.

For the **Randomised** shuffle type, users may specify a set of instruments to be presented in a random order. 

In the module configuration, add the instruments to be shuffled from the drop-down menu. If the `entry-survey` option is set, the shuffling will start after this survey is completed. If not, the shuffling starts after the first survey is completed. Note that as this module uses the `redcap_survey_complete` hook,at least one survey must be completed before the shuffling can start. This means the first survey is not eligible to be shuffled. 

Users may also set an `exit-survey` which will be presented to the respondents after they complete the shuffled surveys. If this is not set, the termination option of the last displayed survey will be used as a fallback.

Users may specify the number of instruments to be displayed. For example, if you have ten instruments, you can choose to display a random five of them before sending respondents to the `exit-survey`.

The order of the administered surveys can be stored in a text field specified by the `sequence-field` configuration option. This field is typically set as `@HIDDEN` and `@READONLY`. You can use the value of this field with `@SETVALUE` on a checkbox field to store the displayed instruments within the record for data quality checking and reporting, for example, to report on the relative proportion of surveys displayed.

Users may configure multiple sequences and limit them to specific longitudinal events. This allows for different sets of surveys in different events, or a single set of surveys with separately shuffled sections. A bridging instrument may be necessary for multiple sequences in one event to act as the `exit_survey` of one sequence and the `entry_survey` of the next to ensure correct navigation through the instruments.

For the **From Field** shuffle type, a variable may be used to supply a predetermined sequence of instruments. This setting is useful where respondents are required to complete surveys in a randomised order in the first case, and then in the same order in subsequent cases. This predetermined sequence overrides the list of instruments to be shuffled and the number to shuffle, and it will prevent the sequence being stored (as it was already stored in the variable it was retrieved from).

### Branching Logic in Module Configuration Settings

Due to a known issue with REDCap's External Module Framework, module sub-settings do not play well with branching logic. This means that configuration settings cannot be hidden where they are not relevant. So, settings relating to the **Randomised** shuffle type and settings relating to the **From Field** shuffle type are always shown.

## Limitations

There is no support for repeating instruments. If one of the shuffled instruments is repeating and configured to allow the respondent to re-take on submission, the respondent is not directed to the next shuffled survey, but instead is directed to a new instance of the repeating instrument. Only once the respondent clicks *Submit* is the `redcap_survey_complete` hook fired, and so only then will they be taken to the next shuffled survey.

The method used to randomly select a survey does not allow for balancing the administration of surveys across a population; each survey page is only aware of which surveys are not yet completed for the current record, and not how many times each survey has been taken.

## TODO

- Investigate support for repeating instruments.
- ~Allow for surveys to be administered in a pre-determined order by reading the sequence field, that is, administering surveys in an order defined elsewhere. Or maybe that's a different module entirely.~ Added in version 2.0.0.
