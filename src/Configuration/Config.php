<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    public const string STATE_LAST_RUN_DATE = 'lastRunDate';

    public const string ENDPOINT_MCF = 'mcf';

    public const string ENDPOINT_REPORTS = 'reports';

    public const string ENDPOINT_DATA_API = 'data-api';

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

    public function skipGenerateSystemTables(): bool
    {
        return $this->getValue(['parameters', 'skipGenerateSystemTables'], false);
    }

    public function getEndpoint(): string
    {
        return $this->getValue(['parameters', 'endpoint']);
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

    public function getQuery(): array
    {
        return $this->getValue(['parameters', 'query'], []);
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

    public function hasLastRunState(): bool
    {
        $query = $this->getQuery();

        if (empty($query['dateRanges'])) {
            return false;
        }

        return !empty(array_filter($query['dateRanges'], fn($v) => $v['startDate'] === self::STATE_LAST_RUN_DATE));
    }

    public function getLastRunState(): array
    {
        $query = $this->getQuery();

        if (!$this->hasLastRunState()) {
            return [];
        }

        $filteredDateRanges = array_filter(
            $query['dateRanges'],
            fn($v) => $v['startDate'] === self::STATE_LAST_RUN_DATE,
        );

        return !empty($filteredDateRanges) ? $filteredDateRanges[0] : [];
    }

    public function processProfiles(string $configDefinition): bool
    {
        if ($configDefinition === OldConfigDefinition::class) {
            return true;
        }

        return in_array($this->getEndpoint(), [Config::ENDPOINT_MCF, Config::ENDPOINT_REPORTS]);
    }

    public function processProperties(string $configDefinition): bool
    {
        if ($configDefinition === OldConfigDefinition::class) {
            return false;
        }

        return in_array($this->getEndpoint(), [Config::ENDPOINT_DATA_API]);
    }

    public function getServiceAccount(): ?array
    {
        $serviceAccount = $this->getArrayValue(['parameters', 'service_account'], []);
        if (empty($serviceAccount)) {
            return null;
        }

        $serviceAccount['private_key'] = $serviceAccount['#private_key'];
        unset($serviceAccount['#private_key']);

        return $serviceAccount;
    }
}
