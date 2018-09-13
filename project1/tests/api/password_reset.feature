Feature: Login as api user

  Background:
    Given I have the payload:
      """
        {
          "username": "admin",
          "password": "password",
          "device": "21E7FABA-B337-42C7-AA72-06A0E645DEA7"
        }
      """
      And the "content-type" header is "application/json"
      And the "accept" header is "application/json"
    When I send POST request to "/auth/login"
    Then response status code should be 200
      And JSON response body should be like:
        """
          {"errors":false}
        """
      And I save "data.token" value as "token"
      And the "Authorization" header is "Bearer <token>"

###########################POSITIVE SCENARIOS############################

  Scenario: Reset password
    Given I have the payload:
      """
        {
          "username": "admin"
        }
      """
    When I send POST request to "/auth/password/reset"
    Then response status code should be 200
      And JSON response body should be like:
        """
          {
            "errors": false,
            "data": true
          }
        """
    Given I have the payload:
      """
        {
          "username": "admin",
          "password": "password",
          "device": "21E7FABA-B337-42C7-AA72-06A0E645DEA7"
        }
      """
    When I send POST request to "/auth/login"
    Then response status code should be 403

###########################NEGATIVE SCENARIOS############################

  Scenario Outline: Reset password for not existing user
    Given I have the payload:
      """
        {
          "username": "<username>"
        }
      """
    When I send POST request to "/auth/password/reset"
    Then response status code should be 422
      And JSON response body should be like:
        """
          {
            "message": "<error>",
            "errors": {
              "username": ["<error>"]
            }
          }
        """
  Examples:
    | username  | error                              |
    | test_user | Cannot find user, please re-enter. |
    |           | The username field is required.    |
