<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    public function hasProfiles(): bool
    {
        return !empty($this->getValue(['parameters', 'profiles'], false));
    }

    public function hasProperties(): bool
    {
        return !empty($this->getValue(['parameters', 'properties'], false));
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

    public function getOutputBucket(): string
    {
        return $this->getValue(['parameters', 'outputBucket'], '');
    }

    public function getQueries(string $configDefinition): array
    {
        if ($configDefinition === OldConfigDefinition::class) {
            $queries = $this->getValue(['parameters', 'queries'], []);
            return array_filter($queries, function ($query) {
                return $query['enabled'];
            });
        } else {
            return [$this->getValue(['parameters'], [])];
        }
    }
}
