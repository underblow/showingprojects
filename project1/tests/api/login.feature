Feature: Login as api user

  Scenario: Test login feature
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

###########################NEGATIVE SCENARIOS############################

  Scenario Outline: Test login feature
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
      And I update json fixture by:
        """
          {"<key>": "<value>"}
        """
    When I send POST request to "/auth/login"
    Then response status code should be 403
  Examples:
    | key      | value |
    | password | test  |
    | username | test  |

