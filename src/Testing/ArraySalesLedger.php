<?php

declare(strict_types=1);

namespace Cbox\Nexus\Testing;

use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\ValueObjects\ActivityQuery;
use Cbox\Nexus\ValueObjects\SellerActivity;

/**
 * An in-memory {@see SalesLedger} for tests and simple setups. Dogfooded by this
 * package's own suite.
 *
 * It comes in the two shapes a real one does. Constructed with a plain state map it
 * answers for a single seller and ignores the subject, which is what a self-hosted
 * adopter's ledger does. Constructed via {@see perSubject()} it holds several, and
 * then a query with NO subject returns null rather than picking one — the refusal
 * the contract asks multi-tenant implementations to make, made here so the shipped
 * fake demonstrates it instead of merely describing it.
 */
readonly class ArraySalesLedger implements SalesLedger
{
    /**
     * @param  array<string, SellerActivity>  $activity  state code => cumulative activity
     * @param  array<string, array<string, SellerActivity>>|null  $bySubject  subject key => state map
     */
    public function __construct(
        private array $activity = [],
        private ?array $bySubject = null,
    ) {}

    /**
     * A ledger holding several sellers, keyed by subject and then by state.
     *
     * @param  array<string, array<string, SellerActivity>>  $bySubject
     */
    public static function perSubject(array $bySubject): self
    {
        return new self([], $bySubject);
    }

    public function activityFor(ActivityQuery $query): ?SellerActivity
    {
        if ($this->bySubject === null) {
            return $this->activity[$query->state->value] ?? null;
        }

        if ($query->subject === null) {
            // Several sellers are held and the question does not say which. Any
            // answer would be somebody's totals reported as somebody else's.
            return null;
        }

        return $this->bySubject[$query->subject->key][$query->state->value] ?? null;
    }
}
