<?php

declare(strict_types=1);

namespace Headless\Accounting\Tax\Providers;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Headless\Accounting\Currency\Contracts\ExchangeRateProvider;
use Headless\Accounting\Exceptions\ProviderUnavailableException;

/**
 * EcbExchangeRateProvider — pulls daily reference rates from the
 * European Central Bank (no key required).
 *
 *     GET https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml
 *
 * Returns XML; we parse it into a flat [currency => rate] map. ECB only
 * publishes a small basket (~32 currencies); pass through what we got
 * and let {@see CurrencyConverter::triangulate()} handle the rest.
 */
final class EcbExchangeRateProvider implements ExchangeRateProvider
{
    private readonly Client $http;

    public function __construct(
        ?Client $http = null,
        private readonly string $feedUrl = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml',
        private readonly float $timeout = 5.0,
    ) {
        $this->http = $http ?? new Client(['timeout' => $this->timeout]);
    }

    public function rates(string $base, array $quotes, CarbonImmutable $at): array
    {
        $feed = $this->loadFeed();

        // ECB rates are EUR-based.
        if ($base !== 'EUR') {
            $baseInEur = 1.0 / ($feed[$base] ?? throw new ProviderUnavailableException("ECB missing $base"));
        } else {
            $baseInEur = 1.0;
        }

        $out = [];
        foreach ($quotes as $q) {
            if ($q === 'EUR') {
                $out[$q] = $baseInEur;

                continue;
            }
            if (! isset($feed[$q])) {
                continue;
            }
            $out[$q] = $baseInEur * $feed[$q];
        }

        return $out;
    }

    /** @return array<string,float> Map of currency=>per-EUR rate. */
    private function loadFeed(): array
    {
        try {
            $xml = (string) $this->http->get($this->feedUrl)->getBody();
        } catch (GuzzleException $e) {
            throw new ProviderUnavailableException('ECB feed unavailable: '.$e->getMessage(), 0, $e);
        }

        $rates = ['EUR' => 1.0];
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if (! $doc) {
            throw new ProviderUnavailableException('ECB feed returned invalid XML.');
        }

        foreach ($doc->Cube->Cube->Cube as $row) {
            $rates[(string) $row['currency']] = (float) $row['rate'];
        }

        return $rates;
    }
}
