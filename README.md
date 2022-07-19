# Google Analytics Extractor

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/keboola/google-analytics-extractor/blob/master/LICENSE.md)

Docker application for extracting data from Google Analytics API (V4).

## Examples

### Profiles Configuration
```json
{
   "parameters": {
      "retriesCount": 1,
      "profiles": [
         {
            "id": "PROFILE_ID",
            "name": "All Web Site Data",
            "webPropertyId": "WEB_PROPORTY_ID",
            "webPropertyName": "WEB_PROPRTY_NAME",
            "accountId": "ACCOUNT_ID",
            "accountName": "ACCOUNT_NAME"
         }
      ],
      "outputTable": "users",
      "query": {
         "metrics": [
            {
               "expression": "ga:users"
            },
            {
               "expression": "ga:pageviews"
            },
            {
               "expression": "ga:bounces"
            }
         ],
         "dimensions": [
            {
               "name": "ga:date"
            },
            {
               "name": "ga:source"
            }
         ],
         "filtersExpression": "",
         "dateRanges": [
            {
               "startDate": "-2 days",
               "endDate": "-1 day"
            }
         ]
      }
   }
}
```

### Properties Configuration
```json
{
   "parameters": {
      "retriesCount": 1,
      "properties": [
         {
            "accountKey": "accounts/ACCOUNT_ID",
            "accountName": "ACCOUNT_NAME",
            "propertyKey": "properties/PROPERTY_ID",
            "propertyName": "PROPERTY_NAME"
         }
      ],
      "outputTable": "users",
      "query": {
         "metrics": [
            {
               "name": "users",
               "expression": "totalUsers"
            },
            {
               "name": "pageviews",
               "expression": "itemViews"
            }
         ],
         "dimensions": [
            {
               "name": "date"
            },
            {
               "name": "source"
            }
         ],
         "filtersExpression": "",
         "dateRanges": [
            {
               "startDate": "-2 days",
               "endDate": "-1 day"
            }
         ]
      }
   }
}
```

### Configuration with data Sampling
```json
{
  "parameters": {
    "retriesCount": 1,
    "properties": [
      {
        "accountKey": "accounts/ACCOUNT_ID",
        "accountName": "ACCOUNT_NAME",
        "propertyKey": "properties/PROPERTY_ID",
        "propertyName": "PROPERTY_NAME"
      }
    ],
    "outputTable": "users",
    "antisampling": "dailyWalk",
    "query": {
      "metrics": [
        {
          "name": "users",
          "expression": "totalUsers"
        },
        {
          "name": "pageviews",
          "expression": "itemViews"
        }
      ],
      "dimensions": [
        {
          "name": "date" // <-- date or dateHour dimension is required
        },
        {
          "name": "source"
        }
      ],
      "filtersExpression": "",
      "dateRanges": [
        {
          "startDate": "-2 months",
          "endDate": "-1 day"
        }
      ]
    }
  }
}
```

Note that this extractor is using [Keboola OAuth Bundle](https://github.com/keboola/oauth-v2-bundle) to store OAuth credentials.
 
## Sampling

Two algorithms are implemented to fight sampling 
- `dailyWalk`
- `adaptive` - for profile reports only

## Development
Clone this repository and init the workspace with following command

```
git clone git@github.com:keboola/google-analytics-extractor.git
cd google-analytics-extractor
docker-compose build
docker-compose run --rm dev composer install --no-interaction
```

You will need working OAuth credentials.
- Go to Googles [OAuth 2.0 Playground](https://developers.google.com/oauthplayground).
- In the configuration (the cog wheel on the top right side) check `Use your own OAuth credentials` and paste your OAuth Client ID and secret.
- Go through the authorization flow and generate Access and Refresh tokens.
- Set `viewId` environment variable to the Google Analytics profile id, you want to run your tests against.

Create and fill up `.env` from previous step.

```
CLIENT_ID=
CLIENT_SECRET=
REFRESH_TOKEN=
ACCESS_TOKEN=
VIEW_ID=
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```




## License

MIT licensed, see [LICENSE](./LICENSE) file.
