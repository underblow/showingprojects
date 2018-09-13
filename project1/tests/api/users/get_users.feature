Feature: Get users list and check filtering and sorting
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

  Scenario: Get the list of users from current user's groups
    When I send GET request to "/users"
    Then response status code should be 200
      And JSON response body should be like:
        """
		{
			"errors": false,
			"data": [
				{
					"id": 1,
					"username": "admin",
					"email": "admin@example.com",
					"is_active": 1,
					"image": null,
					"email_verified": "1",
					"email_verification_code": null,
					"created_at": "2017-06-21 14:39:11",
					"updated_at": null,
					"affiliate_id": 1,
					"is_public_basic": 0,
					"is_public_contact": 0,
					"is_public_job": 0,
					"title": "Mr.",
					"first_name": "Admin",
					"last_name": "Admin",
					"birthday": "",
					"address1": "",
					"address2": "",
					"city": "",
					"state": "",
					"zip": "",
					"primary_phone": "",
					"mobile_phone": "",
					"token_ttl_min": 0,
					"logo": null,
					"full_name": "Admin Admin",
					"positions": [
						{
							"name": "Administrator"
						}
					]
				}
			],
			"total": 1
		}
        """

  Scenario: Get the list of users (not respecting groups)
    When I send GET request to "/users?respectGroup=0"
    Then response status code should be 200
      And JSON response body should be like:
        """
          {
            "errors": false,
            "data": [
              {
                "id": 1,
                "username": "admin",
                "email": "admin@example.com",
                "is_active": 1,
                "email_verified": "1",
                "email_verification_code": null,
                "updated_at": null,
                "affiliate_id": 1,
                "is_public_basic": 0,
                "is_public_contact": 0,
                "is_public_job": 0,
                "title": "Mr.",
                "first_name": "Admin",
                "last_name": "Admin",
                "birthday": "",
                "address1": "",
                "address2": "",
                "city": "",
                "state": "",
                "zip": "",
                "primary_phone": "",
                "mobile_phone": "",
                "token_ttl_min": 0,
                "full_name": "Admin Admin",
                "positions": [
                  {
                    "name": "Administrator"
                  }
                ]
              }
            ],
            "total": 1
          }
        """

  Scenario Outline: Get the sorted list of users
    When I send GET request to "/users?sort=<sort>&respectGroup=0"
    Then response status code should be 200
      And JSON response body should be like:
        """
          {
            "data": [<result>]
          }
        """
	Examples:
    | sort      | result                                                      |
    | full_name | {"id": "1"},{"id": "3"},{"id": "2"},{"id": "5"},{"id": "4"} |
    | position  | {"id": "1"},{"id": "2"},{"id": "3"},{"id": "4"},{"id": "5"} |
    | username  | {"id": "1"},{"id": "3"},{"id": "2"},{"id": "5"},{"id": "4"} |

  Scenario: Get the list of users filtered by position
    When I send GET request to "/users?position=1"
    Then response status code should be 200
      And JSON response body should be like:
        """
          {
            "errors": false,
            "data": [
              {
                "username": "admin",
                "email": "admin@example.com"
              }
            ]
          }
        """

  Scenario: Get users assigned to some tasks
    When I send GET request to "/users/assigned"
    Then response status code should be 200
      And JSON response body should be like:
        """
          {
            "errors": false,
            "data": [
              {"id": "1"},{"id": "2"},{"id": "3"}
            ]
          }
        """
      And JSON response body should NOT be like:
        """
          {
            "errors": false,
            "data": [
              {"id": "4"},{"id": "5"}
            ]
          }
        """