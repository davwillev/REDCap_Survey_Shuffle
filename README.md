# REDCap Survey Shuffle

This REDCap External Module allows users to shuffle surveys such that they are displayed to respondents in a random order, and the order in which they were displayed can be stored in a text variable.

## Installation

### From Module Repository

Install the module from the REDCap module repository and enable in the Control Center, then enable on projects as needed.

### From GitHub

Clone the repository and rename the directory to include a version number, e.g., `survey_shuffle_v1.0.0`, and copy to your modules directory, then enable in Control Center and on projects as needed.

## Usage

In the project's module configuration, add instruments to be shuffled from the drop-down menu. If the `entry-survey` option is set in the configuration, randomisation will commence on completion of this instrument, otherwise randomisation commences upon completion of the first instrument. Since this module makes use of the `redcap_survey_complete` hook, randomisation of instruments cannot take place until at least one survey is completed, and as such, the first survey cannot be shuffled.

An `exit-survey` configuration option allows users to direct respondents to a single survey upon completion of the battery of shuffled surveys. If this is not set, then the survey termination option of the final displayed instrument takes effect.

Users can set a number of instruments to be displayed. For example, in a battery of ten survey instruments, respondents can be administered a random five before being directed to the `exit-survey` (or, the survey termination option of the fifth displayed survey).

Just which survey instruments were administered can be seen by looking at the instruments' complete status fields, but the order in which they were administered can be stored in a text field using the `sequence-field` configuration option in the format `form_3, form_1, form_2`. Typically, this field would be `@HIDDEN` and `@READONLY`. Using `@SETVALUE` on a checkbox field with options coded as the unique names can effectively store the displayed instruments as an enumerated field that is then available for data quality checking and reporting.

Multiple sequences can be configured and sequences can be limited to specific longitudinal events. This allows for a different battery of surveys in different events. It also allows for a single battery of surveys comprising separately shuffled sections. Users may need to configure a bridge instrument to act as the `exit_survey` of one shuffled sequence and the `entry_survey` of the next, otherwise respondents may not be directed through the instruments correctly.

## Limitations

There is no support for repeating instruments. If one of the shuffled instruments is repeating and configured to allow the respondent to re-take on submission, the respondent is not directed to the next shuffled survey, but instead is directed to a new instance of the repeating instrument. Only once the respondent clicks *Submit* is the `redcap_survey_complete` hook fired, and so only then will they be taken to the next shuffled survey.

The method used to randomly select a survey does not allow for balancing the administration of surveys across a population; each survey page is only aware of which surveys are not yet completed for the current record, and not how many times each survey has been taken.

## TODO

- Investigate support for repeating instruments.
- Allow for surveys to be administered in a pre-determined order by reading the sequence field, that is, administering surveys in an order defined elsewhere. Or maybe that's a different module entirely.
