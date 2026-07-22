<?php

declare(strict_types=1);

namespace Cbox\Nexus\Enums;

/**
 * Which sales count toward a state's threshold — mirrors us-tax-data. Guidance for
 * the host on which sales to feed the ledger (gross vs retail vs taxable).
 */
enum NexusSalesBasis: string
{
    case GrossSales = 'gross_sales';
    case RetailSales = 'retail_sales';
    case TaxableSales = 'taxable_sales';
}
