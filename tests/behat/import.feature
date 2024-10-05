@qformat @qformat_gift_answersselect
Feature: Test importing questions from GIFT RSA format.
  In order to reuse questions
  As a teacher
  I need to be able to import them in GIFT RSA format.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname |
      | teacher  | Teacher   |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And I am on the "Course 1" "core_question > course question import" page logged in as "teacher"

  @javascript @_file_upload
  Scenario: import some GIFT questions
    When I set the field "id_format_gift_answersselect" to "1"
    And I upload "question/format/gift_answersselect/tests/fixtures/examples.txt" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 12 questions from file"
    And I should see "Who's actually _buried_ in Grant's tomb?"
    When I press "Continue"
    Then I should see "Difficult question"

  @javascript @_file_upload
  Scenario: import some GIFT RSA questions
    When I set the field "id_format_gift_answersselect" to "1"
    And I upload "question/format/gift_answersselect/tests/fixtures/examplesRSA.txt" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 4 questions from file"
    And I should see "Which of these animals are mammals?"
    When I press "Continue"
    Then I should see "RSA-Q00"
    And I log out
