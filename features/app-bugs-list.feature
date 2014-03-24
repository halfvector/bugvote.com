Feature: app bugs list
	I need to see app's bugs

Scenario: list any available apps
	Given I am on "/app/50518/bugs"
	Then I should see "Handshake"