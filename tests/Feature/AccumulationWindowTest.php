<?php

declare(strict_types=1);

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\Enums\NexusMeasurementPeriod;
use Cbox\Nexus\Enums\NexusStatus;
use Cbox\Nexus\ValueObjects\ActivityQuery;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;
use Cbox\Nexus\ValueObjects\SellerActivity;

// SellerActivity has carried periodStart and periodEnd since it was written, and
// until now NOTHING read them. The engine compared the ledger's declared RULE
// against the state's — "rolling twelve months" against "rolling twelve months" —
// and stopped there.
//
// Two ledgers can agree on the rule and mean different twelve months: one computed
// from a stale as-of date, one served from a cache warmed last quarter, one still
// snapping to month boundaries. The declared rule matches in every case, and the
// verdict comes back clean.

/** A ledger that reports the totals AND the concrete range it accumulated over. */
function ledgerOver(int $dollars, string $from, string $to): SalesLedger
{
    return new readonly class($dollars, $from, $to) implements SalesLedger
    {
        public function __construct(
            private int $dollars,
            private string $from,
            private string $to,
        ) {}

        public function activityFor(ActivityQuery $query): ?SellerActivity
        {
            return new SellerActivity(
                $this->dollars,
                0,
                new DateTimeImmutable($this->from),
                new DateTimeImmutable($this->to),
                period: $query->period,
            );
        }
    };
}

function texas(): EconomicNexusThreshold
{
    // The real one: $500,000 of total Texas revenue in the preceding twelve months.
    return new EconomicNexusThreshold(
        500_000,
        null,
        NexusCombinator::SalesOnly,
        NexusMeasurementPeriod::RollingTwelveMonths,
    );
}

function verdictFor(int $dollars, string $from, string $to): NexusStatus
{
    return test()->nexusEngine(
        thresholds: ['US-TX' => texas()],
        ledger: ledgerOver($dollars, $from, $to),
    )->evaluate(new SubdivisionCode('US-TX'), asOf: new DateTimeImmutable('2026-08-13'))->status;
}

it('accepts the exact window the state measures', function () {
    // 2025-08-14 to 2026-08-13 — a year to the day, both ends counted.
    expect(verdictFor(300_000, '2025-08-14', '2026-08-13'))->toBe(NexusStatus::Below);
});

it('refuses totals accumulated over a window the state does not measure', function () {
    // A cache warmed a quarter ago: the same rule, the wrong twelve months. Nothing
    // about $300,000 against a $500,000 threshold looks wrong, and the seller reads
    // "Below" for a period nobody asked about.
    $engine = test()->nexusEngine(
        thresholds: ['US-TX' => texas()],
        ledger: ledgerOver(300_000, '2025-05-01', '2026-04-30'),
    );

    $evaluation = $engine->evaluate(new SubdivisionCode('US-TX'), asOf: new DateTimeImmutable('2026-08-13'));

    expect($evaluation->status)->toBe(NexusStatus::Unknown)
        ->and($evaluation->reason)->toContain('2025-05-01 to 2026-04-30')
        ->and($evaluation->reason)->toContain('2025-08-14 to 2026-08-13');
});

it('accepts a NARROWER window that has already crossed', function () {
    // Six months of the required year, already over $500,000. The full window
    // contains this one, so its total can only be larger — the seller has
    // definitively crossed. Refusing here would be the dangerous direction: it
    // tells someone who must register that the engine cannot say.
    expect(verdictFor(600_000, '2026-02-14', '2026-08-13'))->toBe(NexusStatus::Triggered);
});

it('refuses a NARROWER window that is still below', function () {
    // Six months and $300,000. The missing half-year could hold anything, so this
    // settles nothing — and "Below" here is exactly the false all-clear the check
    // exists to stop.
    expect(verdictFor(300_000, '2026-02-14', '2026-08-13'))->toBe(NexusStatus::Unknown);
});

it('accepts a BROADER window that is still below', function () {
    // Two years and $300,000. The required year is contained in this, so its total
    // can only be smaller — provably below.
    expect(verdictFor(300_000, '2024-08-14', '2026-08-13'))->toBe(NexusStatus::Below);
});

it('refuses a BROADER window that has crossed', function () {
    // Two years and $600,000. The crossing may belong entirely to the year that
    // falls outside the state's window.
    expect(verdictFor(600_000, '2024-08-14', '2026-08-13'))->toBe(NexusStatus::Unknown);
});

it('checks both windows where the state measures previous OR current year', function () {
    // Kentucky's shape: met if EITHER year crosses, so a ledger matching either one
    // is answering the state's question.
    $threshold = new EconomicNexusThreshold(
        100_000,
        null,
        NexusCombinator::SalesOnly,
        NexusMeasurementPeriod::PreviousOrCurrentCalendarYear,
    );

    $evaluate = fn (string $from, string $to): NexusStatus => test()->nexusEngine(
        thresholds: ['US-KY' => $threshold],
        ledger: ledgerOver(50_000, $from, $to),
    )->evaluate(new SubdivisionCode('US-KY'), asOf: new DateTimeImmutable('2026-08-13'))->status;

    expect($evaluate('2025-01-01', '2025-12-31'))->toBe(NexusStatus::Below)
        ->and($evaluate('2026-01-01', '2026-08-13'))->toBe(NexusStatus::Below)
        // Neither year: the fiscal year of a seller whose books do not run to
        // December.
        ->and($evaluate('2025-07-01', '2026-06-30'))->toBe(NexusStatus::Unknown);
});

it('still takes an undeclared window at face value, with a caveat', function () {
    // Most hosts have not adopted these fields. Refusing where the ledger says
    // nothing would turn the check into a wall for every existing adopter — the
    // caveat already tells the reader the window was never verified.
    $evaluation = test()->nexusEngine(
        thresholds: ['US-TX' => texas()],
        activity: ['US-TX' => new SellerActivity(300_000, 0)],
    )->evaluate(new SubdivisionCode('US-TX'), asOf: new DateTimeImmutable('2026-08-13'));

    expect($evaluation->status)->toBe(NexusStatus::Below)
        ->and($evaluation->caveats)->toContain(
            'The ledger did not declare its accumulation window, so the period was taken at face value.',
        );
});

it('says nothing about dates when the state\'s period is unknown', function () {
    // No period on the threshold means no window to derive, and there is nothing to
    // compare the ledger's dates against. Inventing one would refuse on a rule
    // nobody stated.
    $evaluation = test()->nexusEngine(
        thresholds: ['US-TX' => new EconomicNexusThreshold(500_000, null, NexusCombinator::SalesOnly)],
        ledger: ledgerOver(300_000, '2020-01-01', '2020-12-31'),
    )->evaluate(new SubdivisionCode('US-TX'), asOf: new DateTimeImmutable('2026-08-13'));

    expect($evaluation->status)->toBe(NexusStatus::Below);
});
