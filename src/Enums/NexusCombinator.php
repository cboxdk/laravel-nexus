<?php

declare(strict_types=1);

namespace Cbox\Nexus\Enums;

/**
 * How a state's sales-dollar and transaction thresholds combine — mirrors the
 * us-tax-data `combinator` field.
 */
enum NexusCombinator: string
{
    case SalesOnly = 'sales_only';
    case SalesOrTransactions = 'sales_or_transactions';
    case SalesAndTransactions = 'sales_and_transactions';
}
