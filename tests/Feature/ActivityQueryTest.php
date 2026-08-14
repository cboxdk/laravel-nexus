<?php

declare(strict_types=1);

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Contracts\SalesLedger;
use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\Enums\NexusMeasurementPeriod;
use Cbox\Nexus\Enums\NexusSalesBasis;
use Cbox\Nexus\Testing\ArraySalesLedger;
use Cbox\Nexus\ValueObjects\ActivityQuery;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;
use Cbox\Nexus\ValueObjects\NexusSubject;
use Cbox\Nexus\ValueObjects\SellerActivity;

// The ledger seam used to be activityFor(SubdivisionCode $state) — a question with
// two things missing. It never said WHO to answer for, so a multi-tenant host had
// to infer the seller from ambient context; and it never said what to count, so the
// implementation had to look up the threshold itself to learn that Florida measures
// taxable sales over the previous calendar year while Texas measures gross receipts
// over a rolling twelve months. It had to reach past the contract to satisfy it.

function queryFor(
    string $state,
    ?NexusMeasurementPeriod $period = null,
    ?string $asOf = null,
    ?NexusSubject $subject = null,
): ActivityQuery {
    return new ActivityQuery(
        state: new SubdivisionCode($state),
        subject: $subject,
        period: $period,
        asOf: $asOf === null ? null : new DateTimeImmutable($asOf),
    );
}

// ---- The measurement window ----------------------------------------------

it('turns "previous calendar year" into that whole year', function () {
    $windows = queryFor('US-FL', NexusMeasurementPeriod::PreviousCalendarYear, '2026-08-13')->windows();

    expect($windows)->toHaveCount(1)
        ->and($windows[0]->from->format('Y-m-d'))->toBe('2025-01-01')
        // Inclusive of 31 December: a year that ends on the 31st contains the sales
        // made on the 31st, and an exclusive bound is how they get lost.
        ->and($windows[0]->to->format('Y-m-d'))->toBe('2025-12-31')
        ->and($windows[0]->label)->toBe('2025');
});

it('turns "rolling twelve months" into the trailing year, both ends counted', function () {
    // Texas measures total revenue in the PRECEDING TWELVE MONTHS and the
    // Comptroller evaluates it at any point in time — the clock does not reset in
    // January and it does not snap to month boundaries.
    //
    // An earlier version started this window at the first of the month eleven
    // months back, which quietly dropped the first partial month: a $500,000 sale
    // on 20 August fell outside a window beginning 1 September, and a seller who
    // had unambiguously crossed the threshold was reported Below.
    $windows = queryFor('US-TX', NexusMeasurementPeriod::RollingTwelveMonths, '2026-08-13')->windows();

    expect($windows[0]->from->format('Y-m-d'))->toBe('2025-08-14')
        ->and($windows[0]->to->format('Y-m-d'))->toBe('2026-08-13')
        // Exactly a year, so nothing is counted twice and nothing falls between.
        ->and($windows[0]->from->diff($windows[0]->to)->days + 1)->toBe(365);
});

it('spans a full year from any date, including across a leap day', function () {
    foreach (['2026-01-01', '2026-03-01', '2026-12-31', '2028-02-29'] as $asOf) {
        $window = queryFor('US-TX', NexusMeasurementPeriod::RollingTwelveMonths, $asOf)->windows()[0];

        expect($window->from->diff($window->to)->days + 1)->toBe(365, $asOf);
    }
});

it('gives "previous OR current calendar year" TWO windows, not one long one', function () {
    // The state's test is met if EITHER year crosses. Summing the two together would
    // cross a threshold that neither year on its own reached — a false obligation
    // out of correct data.
    $windows = queryFor('US-KY', NexusMeasurementPeriod::PreviousOrCurrentCalendarYear, '2026-08-13')->windows();

    expect($windows)->toHaveCount(2)
        ->and($windows[0]->from->format('Y-m-d'))->toBe('2025-01-01')
        ->and($windows[0]->to->format('Y-m-d'))->toBe('2025-12-31')
        ->and($windows[1]->from->format('Y-m-d'))->toBe('2026-01-01')
        ->and($windows[1]->to->format('Y-m-d'))->toBe('2026-08-13');
});

it('derives nothing when the period is unknown', function () {
    // An unreachable threshold source leaves the period null. Inventing a window
    // there would have the host sum over dates nobody chose.
    expect(queryFor('US-CA')->windows())->toBe([]);
});

it('defaults to today when asked as of no particular date', function () {
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    expect(queryFor('US-CA')->on()->format('Y-m-d'))->toBe($today);
});

// ---- The subject ----------------------------------------------------------

it('answers for the subject asked about, not whoever came last', function () {
    $ledger = ArraySalesLedger::perSubject([
        'acme' => ['US-CA' => new SellerActivity(600_000, 900)],
        'globex' => ['US-CA' => new SellerActivity(1_000, 2)],
    ]);

    $acme = $ledger->activityFor(queryFor('US-CA', subject: new NexusSubject('acme')));
    $globex = $ledger->activityFor(queryFor('US-CA', subject: new NexusSubject('globex')));

    expect($acme?->salesDollars)->toBe(600_000)
        ->and($globex?->salesDollars)->toBe(1_000);
});

it('refuses to answer for several sellers when the question names none', function () {
    // This is the whole point of carrying the subject. A ledger holding more than
    // one seller and handed no subject has no honest answer — and picking one is
    // how a tenant sees another tenant's totals. Null becomes Unknown upstream.
    $ledger = ArraySalesLedger::perSubject(['acme' => ['US-CA' => new SellerActivity(600_000, 900)]]);

    expect($ledger->activityFor(queryFor('US-CA')))->toBeNull();
});

it('lets a single-seller ledger ignore the subject entirely', function () {
    // A self-hosted adopter has one seller and should not have to invent an
    // identifier for it.
    $ledger = new ArraySalesLedger(['US-CA' => new SellerActivity(600_000, 900)]);

    expect($ledger->activityFor(queryFor('US-CA'))?->salesDollars)->toBe(600_000)
        ->and($ledger->activityFor(queryFor('US-CA', subject: new NexusSubject('anyone')))?->salesDollars)
        ->toBe(600_000);
});

it('refuses an empty subject key rather than matching nothing', function () {
    // An unset tenant id arrives as ''. Accepted, it reads as a valid subject that
    // matches no rows — or worse, matches a row keyed on the empty string.
    expect(fn () => new NexusSubject(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new NexusSubject('   '))->toThrow(InvalidArgumentException::class);
});

it('is the shape a real ledger implements', function () {
    // Dogfooding check: the shipped fake satisfies the published contract, so the
    // examples in the docs are the same shape an adopter writes.
    expect(new ArraySalesLedger)->toBeInstanceOf(SalesLedger::class);
});

// ---- What the engine asks --------------------------------------------------

it('asks the ledger for what the STATE measures, not for whatever it has', function () {
    // The engine resolves the threshold first and puts its terms into the question,
    // so the ledger no longer has to rediscover them to run the right sum.
    $ledger = new class implements SalesLedger
    {
        public ?ActivityQuery $seen = null;

        public function activityFor(ActivityQuery $query): ?SellerActivity
        {
            $this->seen = $query;

            return new SellerActivity(1_000, 1);
        }
    };

    $engine = $this->nexusEngine(
        thresholds: ['US-FL' => new EconomicNexusThreshold(
            100_000,
            null,
            NexusCombinator::SalesOnly,
            NexusMeasurementPeriod::PreviousCalendarYear,
            NexusSalesBasis::TaxableSales,
        )],
        ledger: $ledger,
    );

    $engine->evaluate(new SubdivisionCode('US-FL'), new NexusSubject('acme'), new DateTimeImmutable('2026-08-13'));

    expect($ledger->seen?->period)->toBe(NexusMeasurementPeriod::PreviousCalendarYear)
        ->and($ledger->seen?->basis)->toBe(NexusSalesBasis::TaxableSales)
        ->and($ledger->seen?->subject?->key)->toBe('acme')
        ->and($ledger->seen?->windows()[0]->label)->toBe('2025');
});
