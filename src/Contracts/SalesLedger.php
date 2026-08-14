<?php

declare(strict_types=1);

namespace Cbox\Nexus\Contracts;

use Cbox\Nexus\ValueObjects\ActivityQuery;
use Cbox\Nexus\ValueObjects\SellerActivity;

/**
 * Supplies the seller's CUMULATIVE activity into a state over its measurement
 * period — the running sales/transaction totals economic nexus turns on. This is
 * the host's data (it owns the invoices and knows the measurement window); the
 * package ships no default.
 *
 * **Null means the ledger cannot answer for this state, and the engine reports
 * `Unknown`.** It does NOT mean zero. To assert that nothing was sold — which is
 * what a real ledger returns for a quiet state, since a `SUM` over no rows is 0 —
 * return `new SellerActivity(0, 0)`. The distinction is load-bearing: an unbound
 * or unreachable ledger returning null would otherwise read as "no obligation
 * anywhere", and a seller who crossed every threshold in the country would see a
 * clean board.
 *
 * Declare `basis` and `period` on the returned {@see SellerActivity} wherever you
 * know them. The engine cannot recompute your totals, but it can refuse when they
 * were counted on a different basis than the state measures — and that is the
 * mistake this seam most often carries.
 *
 * The {@see ActivityQuery} tells you what to count and over which dates, so you do
 * not have to derive the state's rules yourself. It also carries WHO to answer
 * for: if you serve more than one seller and `$query->subject` is null, return null
 * rather than guessing from ambient context — see {@see ActivityQuery::$subject}.
 */
interface SalesLedger
{
    public function activityFor(ActivityQuery $query): ?SellerActivity;
}
