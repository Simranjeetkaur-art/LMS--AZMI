@mod @mod_flashdeck
Feature: Teachers author flashcard decks and students study them
  In order to help learners retain dense material
  As a teacher
  I need to create decks of cards that students can study by active recall

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity  | name               | course | idnumber |
      | flashdeck | Week 1 terminology | C1     | fd1      |

  Scenario: A student sees the deck with the card prompt showing
    Given the following "mod_flashdeck > cards" exist:
      | flashdeck          | cardtype | front                 | back         |
      | Week 1 terminology | basic    | What does -itis mean? | Inflammation |
    When I am on the "Week 1 terminology" "flashdeck activity" page logged in as student1
    Then I should see "What does -itis mean?"
    And I should see "Show answer"

  Scenario: A student with no cards sees the empty state, not an error
    When I am on the "Week 1 terminology" "flashdeck activity" page logged in as student1
    Then I should see "There are no cards in this deck yet."

  Scenario: A teacher adds a basic card through the editor
    Given I am on the "Week 1 terminology" "flashdeck activity" page logged in as teacher1
    When I follow "Manage cards"
    And I follow "Basic (two-sided)"
    And I set the following fields to these values:
      | Front (prompt) | What does the prefix brady- mean? |
      | Back (answer)  | Slow                              |
      | Tags           | emd101,prefixes                   |
    And I press "Save changes"
    Then I should see "Card saved."
    And I should see "What does the prefix brady- mean?"
    And I should see "emd101,prefixes"

  Scenario: A teacher loads the bundled EMD-101 sample deck
    Given I am on the "Week 1 terminology" "flashdeck activity" page logged in as teacher1
    When I follow "Manage cards"
    And I follow "Load sample deck (EMD-101 Week 1)"
    Then I should see "sample cards added to the deck."
    And I should see "cardiology"
    And I should see "Term dissection"

  Scenario: A teacher deletes a card after confirming
    Given the following "mod_flashdeck > cards" exist:
      | flashdeck          | cardtype | front       | back      |
      | Week 1 terminology | basic    | Delete me   | Goodbye   |
    And I am on the "Week 1 terminology" "flashdeck activity" page logged in as teacher1
    When I follow "Manage cards"
    And I click on "Delete" "link" in the "Delete me" "table_row"
    And I press "Continue"
    Then I should see "Card deleted."
    And I should see "There are no cards in this deck yet."

  Scenario: A student cannot manage cards
    When I am on the "Week 1 terminology" "flashdeck activity" page logged in as student1
    Then I should not see "Manage cards"

  Scenario: A student studies with spaced repetition using the four grade buttons
    Given the following "mod_flashdeck > cards" exist:
      | flashdeck          | cardtype | front | back |
      | Week 1 terminology | basic    | Q1    | A1   |
      | Week 1 terminology | basic    | Q2    | A2   |
    When I am on the "Week 1 terminology" "flashdeck activity" page logged in as student1
    Then I should see "Q1"
    And I should see "How well did you recall it?"
    And I should see "Again"
    And I should see "Easy"
    # Good on a new card moves it into a 10-minute learning step,
    # so the other new card is served next.
    When I press "Good"
    Then I should see "Q2"
    # Both cards now wait in learning steps; the learn-ahead window
    # serves them early so the session can finish what it started.
    When I press "Good"
    Then I should see "Q1"
    When I press "Good"
    Then I should see "Q2"
    # The second Good graduates each card to a one-day interval.
    When I press "Good"
    Then I should see "All caught up!"
    And I should see "Next review:"

  Scenario: A teacher authors a cloze card and a student types into its blank
    Given I am on the "Week 1 terminology" "flashdeck activity" page logged in as teacher1
    When I follow "Manage cards"
    And I follow "Cloze / type the answer"
    And I set the field "Text with blanks" to "The suffix [[‑itis|itis]] means inflammation."
    And I press "Save changes"
    Then I should see "Card saved."
    And I should see "The suffix … means inflammation."
    When I am on the "Week 1 terminology" "flashdeck activity" page logged in as student1
    Then I should see "The suffix"
    And I should see "means inflammation."
    And "input" "css_element" should exist in the ".flashdeck-clozetext" "css_element"

  Scenario: A teacher authors a matching card served with alphabetical options
    Given I am on the "Week 1 terminology" "flashdeck activity" page logged in as teacher1
    When I follow "Manage cards"
    And I follow "Matching"
    And I set the following fields to these values:
      | Prompt        | Match each prefix to its meaning |
      | Left item 1   | tachy-                           |
      | Right match 1 | fast                             |
      | Left item 2   | brady-                           |
      | Right match 2 | slow                             |
    And I press "Save changes"
    Then I should see "Card saved."
    When I am on the "Week 1 terminology" "flashdeck activity" page logged in as student1
    Then I should see "Match each prefix to its meaning"
    And I should see "tachy-"
    And ".flashdeck-matchselect" "css_element" should exist

  Scenario: A student can switch to browsing all cards without scheduling
    Given the following "mod_flashdeck > cards" exist:
      | flashdeck          | cardtype | front | back |
      | Week 1 terminology | basic    | Q1    | A1   |
    When I am on the "Week 1 terminology" "flashdeck activity" page logged in as student1
    And I follow "Browse all cards"
    Then I should see "Q1"
    And I should see "Study (spaced repetition)"
