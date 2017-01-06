#!/bin/bash
docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/google-analytics-extractor quay.io/keboola/google-analytics-extractor:$TRAVIS_TAG
docker images
docker push quay.io/keboola/google-analytics-extractor:$TRAVIS_TAG
