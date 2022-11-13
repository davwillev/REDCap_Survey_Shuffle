# REDCap Survey Shuffle

This REDCap External Module allows users to shuffle surveys such that they are displayed to respondents in a random order.

## Installation

### From Module Repository

Install the module from the REDCap module repository and enable in the Control Center, then enable on projects as needed.

### From GitHub

Clone the repository and rename the directory to include a version number, e.g., `survey_shuffle_v1.0.0`, and copy to your modules directory, then enable in Control Center and on projects as needed.

## Usage

In the module configuration, add instruments to be shuffled from the drop-down menu. The surveys should be contiguous or weird things may happen. If the `prior-survey` option is set in the configuration, randomisation will commence on completion of this prior survey, otherwise randomisation commences upon completion of the first instrument. Since this module makes use of the `redcap_survey_complete` hook, randomisation of instruments cannot take place until at least one survey is completed.

An `exit-survey` configuration option allows users to direct respondents to a single survey upon completion of the battery of shuffled surveys. If this is not set, then the survey termination option of the final displayed instrument takes effect.

Users can set a number of instruments to be displayed. For example, in a battery of ten survey instruments, respondents can be administered a random 5 before being directed to the `exit-survey` (or, the survey termination option of the fifth displayed survey). In another example, randomly displaying one of two instruments can allow for A/B testing.

Just which survey instruments were administered can be seen by looking at the instruments' complete status fields, but the order in which they were administered can be stored in a text field using the `sequence-field` configuration option in the format `form_3, form_1, form_2, `. Typically, this field would be `@HIDDEN` and `@READONLY`.

## Limitations

Currently this module is not event-aware and has not been tested in a longitudinal context.

Similarly, there is no support for repeating instruments, and I'm not even sure how that would even work.

The method used to randomly select a survey does not allow for balancing the administration of surveys across a population; each survey page is only aware of which surveys are not yet completed for the current record, and not how many times each survey has been taken.

## TODO

- Investigate support for longitudinal projects and repeating instruments.
- Allow for surveys to be administered in a pre-determined order by reading the sequence field, that is, administering surveys in an order defined elsewhere. Or maybe that's a different module entirely.
