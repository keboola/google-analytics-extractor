<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use InvalidArgumentException;
use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    public function hasProfiles(): bool
    {
        try {
            $this->getValue(['parameters', 'profiles']);
        } catch (InvalidArgumentException $e) {
            return false;
        }
        return true;
    }

    public function hasProperties(): bool
    {
        try {
            $this->getValue(['parameters', 'properties']);
        } catch (InvalidArgumentException $e) {
            return false;
        }
        return true;
    }

    public function getProfiles(): array
    {
        return $this->getValue(['parameters', 'profiles']);
    }

    public function getProperties(): array
    {
        return $this->getValue(['parameters', 'properties']);
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
