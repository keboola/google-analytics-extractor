<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor;

use GuzzleHttp\Psr7\Response;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\GoogleAnalyticsExtractor\GoogleAnalytics\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class ValidatorTest extends TestCase
{
    public function testValidValidateProfiles(): void
    {
        $logger = new TestLogger();
        $validator = new Validator(
            new Client(
                $this->getMockRestApi(),
                $logger,
                []
            ),
            $logger
        );

        $profiles = [
            [
                'id' => 184062725,
                'name' => 'All Web Site Data',
                'webPropertyId' => 'UA-128209249-1',
                'webPropertyName' => 'Keboola Website',
                'accountId' => 128209249,
                'accountName' => 'Keboola Website',
            ],
        ];

        $validProfiles = $validator->validateProfiles($profiles);

        $this->assertEquals($profiles, iterator_to_array($validProfiles));
        $this->assertFalse($logger->hasWarningThatContains('Cannot access to profile'));
    }

    public function testInvalidValidateProfiles(): void
    {
        $logger = new TestLogger();
        $validator = new Validator(
            new Client(
                $this->getMockRestApi(),
                $logger,
                []
            ),
            $logger
        );

        $profiles = [
            [
                'id' => 184062725,
                'name' => 'All Web Site Data',
                'webPropertyId' => 'UA-128209249-1',
                'webPropertyName' => 'Keboola Website',
                'accountId' => 128209249,
                'accountName' => 'Keboola Website',
            ],
            [
                'id' => 123456,
                'name' => 'Invalid profile',
                'webPropertyId' => 'UA-128209249-1',
                'webPropertyName' => 'Keboola Website',
                'accountId' => 128209249,
                'accountName' => 'Keboola Website',
            ],
        ];

        $validProfiles = $validator->validateProfiles($profiles);

        $expectedProfiles = $profiles;
        unset($expectedProfiles[1]);

        $this->assertEquals($expectedProfiles, iterator_to_array($validProfiles));
        $this->assertTrue(
            $logger->hasWarningThatContains('Cannot access to profile "Invalid profile".')
        );
    }

    public function testValidValidateProperties(): void
    {
        $logger = new TestLogger();
        $validator = new Validator(
            new Client(
                $this->getMockRestApi(),
                $logger,
                []
            ),
            $logger
        );

        $properties = [
            [
                'accountKey' => 'accounts/185283969',
                'accountName' => 'Keboola',
                'propertyKey' => 'properties/255885884',
                'propertyName' => 'users',
            ],
        ];

        $validProperties = $validator->validateProperties($properties);

        $this->assertEquals($properties, iterator_to_array($validProperties));
        $this->assertFalse($logger->hasWarningThatContains('Cannot access to property'));
    }

    public function testInvalidValidateProperties(): void
    {
        $logger = new TestLogger();
        $validator = new Validator(
            new Client(
                $this->getMockRestApi(),
                $logger,
                []
            ),
            $logger
        );

        $properties = [
            [
                'accountKey' => 'accounts/185283969',
                'accountName' => 'Keboola',
                'propertyKey' => 'properties/255885884',
                'propertyName' => 'users',
            ],
            [
                'accountKey' => 'accounts/123456',
                'accountName' => 'Keboola',
                'propertyKey' => 'properties/123456',
                'propertyName' => 'Invalid property',
            ],
        ];

        $validProperties = $validator->validateProperties($properties);

        $expectedProperties = $properties;
        unset($expectedProperties[1]);

        $this->assertEquals($expectedProperties, iterator_to_array($validProperties));
        $this->assertTrue(
            $logger->hasWarningThatContains('Cannot access to property "Invalid property".')
        );
    }

    public function returnMockServerRequest(string $url): Response
    {
        /** @phpcs:disable */
        switch ($url) {
            case sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, Client::PAGE_SIZE):
                return new Response(
                    200,
                    [],
                    '{"accountSummaries":[{"name":"accountSummaries/128209249","account":"accounts/128209249","displayName":"Keboola Website"},{"name":"accountSummaries/185283969","account":"accounts/185283969","displayName":"Ondřej Jodas","propertySummaries":[{"property":"properties/255885884","displayName":"users"}]},{"name":"accountSummaries/52541130","account":"accounts/52541130","displayName":"Keboola Status Blog"}]}'
                );
            case sprintf('%s?max-results=%d', Client::ACCOUNT_PROFILES_URL, Client::PAGE_SIZE):
                return new Response(
                    200,
                    [],
                    '{"kind":"analytics#profiles","username":"ondrej.jodas@keboola.com","totalResults":2,"startIndex":1,"itemsPerPage":1000,"items":[{"id":"88156763","accountId":"52541130","webPropertyId":"UA-52541130-1","name":"All Web Site Data"},{"id":"184062725","accountId":"128209249","webPropertyId":"UA-128209249-1","name":"All Web Site Data"}]}'
                );
        }
        /** @phpcs:enable */
        return new Response(200, [], '');
    }

    private function getMockRestApi(): RestApi
    {
        $restApi = $this->createMock(RestApi::class);
        $restApi
            ->method('request')
            ->with($this->logicalOr(
                sprintf('%s?pageSize=%d', Client::ACCOUNT_PROPERTIES_URL, Client::PAGE_SIZE),
                sprintf('%s?max-results=%d', Client::ACCOUNT_PROFILES_URL, Client::PAGE_SIZE)
            ))
            ->will($this->returnCallback([$this, 'returnMockServerRequest']));

        return $restApi;
    }
}
