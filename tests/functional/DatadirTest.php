<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Functional;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;

class DatadirTest extends DatadirTestCase
{
    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        $credentialsData = [
            'access_token' => getenv('ACCESS_TOKEN'),
            'refresh_token' => getenv('REFRESH_TOKEN'),
        ];
        putenv(sprintf(
            'CREDENTIALS_DATA=%s',
            json_encode($credentialsData),
        ));

        parent::__construct($name, $data, $dataName);
    }

    /**
     * @dataProvider provideDatadirSpecifications
     */
    public function testDatadir(DatadirTestSpecificationInterface $specification): void
    {
        $tempDatadir = $this->getTempDatadir($specification);

        /**
         * @var array{
         *     "parameters": ?array{
         *         "service_account": string,
         *     }
         * } $config
         */
        $config = json_decode(
            (string) file_get_contents($tempDatadir->getTmpFolder() . '/config.json'),
            true,
        );
        if (array_key_exists('service_account', $config['parameters'] ?? [])) {
            /**
             * @var array{
             *     "private_key": string,
             * } $serviceAccount
             */
            $serviceAccount = json_decode($config['parameters']['service_account'], true);
            $serviceAccount['#private_key'] = $serviceAccount['private_key'];
            unset($serviceAccount['private_key']);
            $config['parameters']['service_account'] = $serviceAccount;
        }
        file_put_contents($tempDatadir->getTmpFolder() . '/config.json', json_encode($config));
        $process = $this->runScript($tempDatadir->getTmpFolder());

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
    }
}
