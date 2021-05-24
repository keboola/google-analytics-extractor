<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Extractor\Antisampling;

interface IAntisampling
{
    public function adaptive(array $query, array $report): void;
    public function dailyWalk(array $query): void;
}
