Feature: app bugs list
	In order to see a bug's details
	I need to be able to click on a bug

Scenario: selected a bug from the list
	Given I am on "/app/50518/bugs"
	And I follow "Handshake"
	Then I should see "oh my!"
