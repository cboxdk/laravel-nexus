<?php

declare(strict_types=1);

namespace Cbox\Nexus\Enums;

/**
 * Which sales count toward a state's threshold — mirrors us-tax-data. Guidance for
 * the host on which sales to feed the ledger (gross vs retail vs taxable).
 *
 * The three are NESTED, and that is load-bearing: every taxable sale is a retail
 * sale, and every retail sale is a gross sale. So a total counted on one basis
 * bounds the total on any other, which lets the engine decide some comparisons
 * even when the host counted something other than what the state measures. See
 * {@see breadth()}.
 */
enum NexusSalesBasis: string
{
    case GrossSales = 'gross_sales';
    case RetailSales = 'retail_sales';
    case TaxableSales = 'taxable_sales';

    /**
     * How inclusive this basis is — higher counts more sales.
     *
     * Gross ⊇ retail ⊇ taxable, so a gross total is always ≥ the retail total for
     * the same period, which is always ≥ the taxable total.
     */
    public function breadth(): int
    {
        return match ($this) {
            self::GrossSales => 3,
            self::RetailSales => 2,
            self::TaxableSales => 1,
        };
    }

    /** Whether this basis counts at least as many sales as `$other`. */
    public function isAtLeastAsBroadAs(self $other): bool
    {
        return $this->breadth() >= $other->breadth();
    }

    /** A human-readable name for reasons and caveats ("gross sales"). */
    public function label(): string
    {
        return str_replace('_', ' ', $this->value);
    }
}
