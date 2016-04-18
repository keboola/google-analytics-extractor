# Google Analytics Extractor


## Example configuration

    
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

