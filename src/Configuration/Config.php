<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    public function getProfiles(): array
    {
        return $this->getValue(['parameters', 'profiles']);
    }

    public function getRetries(): int
    {
        return (int) $this->getValue(['parameters', 'retriesCount'], 1);
    }

    public function getNonConflictPrimaryKey(): bool
    {
        return $this->getValue(['parameters', 'nonConflictPrimaryKey']);
    }
}
