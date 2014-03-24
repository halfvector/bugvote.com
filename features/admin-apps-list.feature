Feature: admin apps list
	In order to see the apps list
	As an admin
	I need to be able to list the apps

Scenario: list any available apps
	Given I am on "/admin/apps"
	Then I should see "Skype"