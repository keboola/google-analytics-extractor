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
            json_encode($credentialsData)
        ));

        parent::__construct($name, $data, $dataName);
    }
}
