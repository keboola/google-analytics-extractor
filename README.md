# Google Analytics Extractor

[![Build Status](https://travis-ci.org/keboola/google-analytics-extractor.svg?branch=master)](https://travis-ci.org/keboola/google-analytics-extractor)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/keboola/google-analytics-extractor/blob/master/LICENSE.md)

Docker application for extracting data from Google Analytics API (V4).

## Example configuration

```yaml
parameters:
  profiles:
    -
      id: PROFILE_ID
      name: 'All Web Site Data'
      webPropertyId: WEB_PROPRTY_ID
      webPropertyName: WEB_PROPRTY_ID
      accountId: ACCOUNT_ID
      accountName: ACCOUNT_NAME

  queries:
    -
      id: 0
      name: query
      metrics: [ga:users]
      dimensions: [ga:date]
      filters: []
      profileId: #optional
      date_ranges:
        -
          since:
          until:
    -
      id: 1
      name: query-sfsdfs
      metrics: [ga:users]
      dimensions: [ga:date]
      filters: []
      date_ranges:
        -
          since:
          until:
    -
      id: 2
      name: something
      metrics: [ga:users]
      dimensions: [ga:date]
      filters: []
```

## Development

App is developed localy.

1. Clone from repository: `git clone git@github.com:keboola/google-analytics-extractor.git`
2. Change directory: `cd google-analytics-extractor`
3. Install dependencies: `composer install --no-interaction`
4. Create `tests.sh` file from template `tests.sh.template`. 
5. You will need working OAuth credentials. Go to Googles [OAuth 2.0 Playground](https://developers.google.com/oauthplayground). 
   In the configuration (the cog wheel on the top right side) check `Use your own OAuth credentials` and paste your OAuth Client ID and secret.
   Then go throug the authorization flow and generate Access and Refresh tokens. Copy and paste them into the tests.sh file.
   Set `viewId` environment variable to the Google Analytics profile id, you want to run your tests against.
6. Run the tests: `./tests.sh`
  


