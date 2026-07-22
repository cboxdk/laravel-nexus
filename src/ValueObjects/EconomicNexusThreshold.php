<?php

declare(strict_types=1);

namespace Cbox\Nexus\ValueObjects;

use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\Enums\NexusMeasurementPeriod;
use Cbox\Nexus\Enums\NexusSalesBasis;

/**
 * One state's post-*Wayfair* economic-nexus threshold: the annual sales-dollar
 * figure, an optional transaction count, and how the two combine — plus the
 * dimensions that decide WHICH sales, over WHICH window, count toward it (the
 * measurement period, the sales basis, whether marketplace sales are included).
 * The measurement dimensions are advisory: they tell the host how to accumulate a
 * {@see SellerActivity} correctly; the engine's crossing test uses the figures.
 */
readonly class EconomicNexusThreshold
{
    public function __construct(
        public int $salesDollars,
        public ?int $transactions,
        public NexusCombinator $combinator,
        public ?NexusMeasurementPeriod $measuringPeriod = null,
        public ?NexusSalesBasis $salesBasis = null,
        public ?bool $includesMarketplaceSales = null,
    ) {}

    /**
     * Whether the given cumulative sales (whole dollars) and transaction count
     * cross this state's threshold.
     */
    public function isMet(int $salesDollars, int $transactions): bool
    {
        $salesMet = $salesDollars >= $this->salesDollars;
        $transactionsMet = $this->transactions !== null && $transactions >= $this->transactions;

        return match ($this->combinator) {
            NexusCombinator::SalesOnly => $salesMet,
            NexusCombinator::SalesOrTransactions => $salesMet || $transactionsMet,
            NexusCombinator::SalesAndTransactions => $salesMet && $transactionsMet,
        };
    }

    /**
     * How close the activity is to crossing, as a ratio (1.0 = at the threshold).
     * For OR states the nearer measure counts (either triggers); for AND states
     * the further one counts (both must be reached); sales-only uses sales.
     */
    public function progress(int $salesDollars, int $transactions): float
    {
        $salesRatio = $this->salesDollars > 0 ? $salesDollars / $this->salesDollars : 0.0;

        if ($this->transactions === null || $this->transactions <= 0) {
            return $salesRatio;
        }

        $transactionRatio = $transactions / $this->transactions;

        return match ($this->combinator) {
            NexusCombinator::SalesOnly => $salesRatio,
            NexusCombinator::SalesOrTransactions => max($salesRatio, $transactionRatio),
            NexusCombinator::SalesAndTransactions => min($salesRatio, $transactionRatio),
        };
    }

    /** A short human-readable description, e.g. "$100,000 or 200 transactions". */
    public function describe(): string
    {
        $sales = '$'.number_format($this->salesDollars);

        if ($this->transactions === null) {
            return $sales;
        }

        $joiner = $this->combinator === NexusCombinator::SalesAndTransactions ? ' and ' : ' or ';

        return $sales.$joiner.number_format($this->transactions).' transactions';
    }
}
