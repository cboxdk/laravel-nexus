<?php

declare(strict_types=1);

use Cbox\Geo\ValueObjects\SubdivisionCode;
use Cbox\Nexus\Enums\NexusCombinator;
use Cbox\Nexus\Enums\NexusMeasurementPeriod;
use Cbox\Nexus\Enums\NexusSalesBasis;
use Cbox\Nexus\Enums\NexusStatus;
use Cbox\Nexus\ValueObjects\EconomicNexusThreshold;
use Cbox\Nexus\ValueObjects\SellerActivity;

function state(string $code): SubdivisionCode
{
    return new SubdivisionCode($code);
}

it('is below when cumulative sales are under the threshold', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-CA' => $this->salesThreshold(500_000)],
        activity: ['US-CA' => $this->activity(100_000)],
    );

    $e = $engine->evaluate(state('US-CA'));

    expect($e->status)->toBe(NexusStatus::Below)
        ->and($e->needsAction())->toBeFalse()
        ->and($e->progress)->toBe(0.2);
});

it('is approaching within the warning band', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-TX' => $this->salesThreshold(100_000)],
        activity: ['US-TX' => $this->activity(85_000)],
        approachingRatio: 0.8,
    );

    expect($engine->evaluate(state('US-TX'))->status)->toBe(NexusStatus::Approaching);
});

it('is triggered when the economic threshold is crossed', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-TX' => $this->salesThreshold(100_000)],
        activity: ['US-TX' => $this->activity(150_000)],
    );

    $e = $engine->evaluate(state('US-TX'));

    expect($e->status)->toBe(NexusStatus::Triggered)
        ->and($e->needsAction())->toBeTrue();
});

it('is triggered by physical presence regardless of sales', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-FL' => $this->salesThreshold(100_000)],
        activity: ['US-FL' => $this->activity(1_000)],
        physical: ['US-FL'],
    );

    $e = $engine->evaluate(state('US-FL'));

    expect($e->status)->toBe(NexusStatus::Triggered)
        ->and($e->physicalPresence)->toBeTrue();
});

it('reports registered when the seller already holds a registration', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-NY' => $this->salesThreshold(500_000)],
        activity: ['US-NY' => $this->activity(600_000)], // would otherwise trigger
        registered: ['US-NY'],
    );

    expect($engine->evaluate(state('US-NY'))->status)->toBe(NexusStatus::Registered);
});

// An unresolvable THRESHOLD and an absence of ACTIVITY are different answers and must not share
// a status. "No threshold known" is the engine not knowing; "no activity" is a confident nothing.
//
// This matters because the threshold source is remote: a firewalled or misconfigured self-hosted
// deployment resolves null for EVERY state. Reporting `Below` there showed a clean board while the
// seller crossed thresholds nationwide and under-collected sales tax, with no signal anywhere.
it('reports unknown — not below — when the threshold cannot be resolved', function () {
    $engine = $this->nexusEngine(activity: ['US-CA' => $this->activity(9_000_000)]);

    $evaluation = $engine->evaluate(state('US-CA'));

    expect($evaluation->status)->toBe(NexusStatus::Unknown)
        ->and($evaluation->status->needsAction())->toBeTrue()
        ->and($evaluation->reason)->toContain('cannot be evaluated');
});

// The same reasoning, one seam further out. A ledger returning null is not a seller
// with no sales — it is a ledger that cannot answer: unbound, unreachable, or not
// yet populated. Reading that as `Below` hands a fresh install a clean board across
// all fifty states, which is the failure the status above exists to prevent.
it('reports unknown — not below — when the ledger cannot answer', function () {
    $engine = $this->nexusEngine(thresholds: ['US-CA' => $this->salesThreshold(100_000)]);

    $evaluation = $engine->evaluate(state('US-CA'));

    expect($evaluation->status)->toBe(NexusStatus::Unknown)
        ->and($evaluation->status->needsAction())->toBeTrue()
        ->and($evaluation->reason)->toContain('SalesLedger');
});

it('reports below when the ledger positively asserts zero', function () {
    // A real ledger SUMs over no rows and gets 0 — a confident nothing, and how a
    // host says so.
    $engine = $this->nexusEngine(
        thresholds: ['US-CA' => $this->salesThreshold(100_000)],
        activity: ['US-CA' => new SellerActivity(0, 0)],
    );

    $evaluation = $engine->evaluate(state('US-CA'));

    expect($evaluation->status)->toBe(NexusStatus::Below)
        ->and($evaluation->status->needsAction())->toBeFalse();
});

// ---- Totals counted against a different question are refused -------------

// The bases nest — gross ⊇ retail ⊇ taxable — so a total on the wrong one still
// BOUNDS the right one. Half the mismatches are therefore decidable, and refusing
// them is not caution, it is withholding an answer the engine can prove.

it('still decides when the ledger counted broader and came in below', function () {
    // Host counted GROSS ($1,000) against Colorado's RETAIL threshold. Retail is a
    // subset of gross, so the retail figure can only be smaller — below on gross
    // proves below on retail.
    $threshold = new EconomicNexusThreshold(
        100_000, null, NexusCombinator::SalesOnly,
        salesBasis: NexusSalesBasis::RetailSales,
    );

    $engine = $this->nexusEngine(
        thresholds: ['US-CO' => $threshold],
        activity: ['US-CO' => new SellerActivity(1_000, 5, basis: NexusSalesBasis::GrossSales)],
    );

    $evaluation = $engine->evaluate(state('US-CO'));

    expect($evaluation->status)->toBe(NexusStatus::Below)
        // Sound, but reached by bounding rather than like-for-like — say so.
        ->and($evaluation->isQualified())->toBeTrue();
});

it('still decides when the ledger counted narrower and is already over', function () {
    // The dangerous direction. Host counted TAXABLE ($5m) against California's
    // GROSS threshold. Gross is a superset, so it can only be larger — a seller
    // this far over has definitively crossed, and telling them "cannot be
    // evaluated" instead of "register" is the worst answer available.
    $threshold = new EconomicNexusThreshold(
        500_000, null, NexusCombinator::SalesOnly,
        salesBasis: NexusSalesBasis::GrossSales,
    );

    $engine = $this->nexusEngine(
        thresholds: ['US-CA' => $threshold],
        activity: ['US-CA' => new SellerActivity(5_000_000, 9_000, basis: NexusSalesBasis::TaxableSales)],
    );

    expect($engine->evaluate(state('US-CA'))->status)->toBe(NexusStatus::Triggered);
});

it('refuses only the genuinely undecidable half', function () {
    $gross = new EconomicNexusThreshold(100_000, null, NexusCombinator::SalesOnly, salesBasis: NexusSalesBasis::GrossSales);
    $taxable = new EconomicNexusThreshold(100_000, null, NexusCombinator::SalesOnly, salesBasis: NexusSalesBasis::TaxableSales);

    $engine = $this->nexusEngine(
        thresholds: ['US-AZ' => $gross, 'US-FL' => $taxable],
        activity: [
            // Counted narrower and BELOW: the broader figure might still be over.
            'US-AZ' => new SellerActivity(50_000, 100, basis: NexusSalesBasis::TaxableSales),
            // Counted broader and MET: the narrower figure might be under.
            'US-FL' => new SellerActivity(150_000, 400, basis: NexusSalesBasis::GrossSales),
        ],
    );

    expect($engine->evaluate(state('US-AZ'))->status)->toBe(NexusStatus::Unknown)
        ->and($engine->evaluate(state('US-FL'))->status)->toBe(NexusStatus::Unknown);
});

it('states each ledger-wide caveat once across a whole report', function () {
    // A host that has not adopted the optional fields would otherwise collect the
    // same sentence on all fifty states — the cry-wolf failure NotApplicable
    // exists to avoid, reintroduced one field over.
    $threshold = new EconomicNexusThreshold(
        100_000, null, NexusCombinator::SalesOnly,
        measuringPeriod: NexusMeasurementPeriod::PreviousCalendarYear,
        salesBasis: NexusSalesBasis::TaxableSales,
    );

    $engine = $this->nexusEngine(
        thresholds: ['US-CA' => $threshold, 'US-TX' => $threshold, 'US-NY' => $threshold],
        activity: [
            'US-CA' => new SellerActivity(1_000, 5),
            'US-TX' => new SellerActivity(2_000, 6),
            'US-NY' => new SellerActivity(3_000, 7),
        ],
    );

    $report = $engine->report([state('US-CA'), state('US-TX'), state('US-NY')]);

    // Three states, two concerns each, but only two distinct things to tell anyone.
    expect($report->distinctCaveats())->toHaveCount(2);
});

it('refuses a verdict when the ledger counted a different basis than the state measures', function () {
    // Florida measures TAXABLE sales. $120k gross of which $70k is exempt is not
    // $120k of taxable sales, and comparing it to Florida's $100k answers a
    // question Florida never asked.
    $threshold = new EconomicNexusThreshold(
        100_000, null, NexusCombinator::SalesOnly,
        salesBasis: NexusSalesBasis::TaxableSales,
    );

    $engine = $this->nexusEngine(
        thresholds: ['US-FL' => $threshold],
        activity: ['US-FL' => new SellerActivity(120_000, 400, basis: NexusSalesBasis::GrossSales)],
    );

    $evaluation = $engine->evaluate(state('US-FL'));

    expect($evaluation->status)->toBe(NexusStatus::Unknown)
        ->and($evaluation->reason)->toContain('taxable sales')
        ->and($evaluation->reason)->toContain('gross sales');
});

it('refuses a verdict when the ledger accumulated over a different window', function () {
    $threshold = new EconomicNexusThreshold(
        500_000, null, NexusCombinator::SalesOnly,
        measuringPeriod: NexusMeasurementPeriod::RollingTwelveMonths,
    );

    $engine = $this->nexusEngine(
        thresholds: ['US-TX' => $threshold],
        activity: ['US-TX' => new SellerActivity(40_000, 20, period: NexusMeasurementPeriod::CurrentCalendarYear)],
    );

    expect($engine->evaluate(state('US-TX'))->status)->toBe(NexusStatus::Unknown);
});

it('gives an unqualified verdict when the declared basis and window match', function () {
    $threshold = new EconomicNexusThreshold(
        100_000, null, NexusCombinator::SalesOnly,
        measuringPeriod: NexusMeasurementPeriod::PreviousCalendarYear,
        salesBasis: NexusSalesBasis::TaxableSales,
    );

    $engine = $this->nexusEngine(
        thresholds: ['US-FL' => $threshold],
        activity: ['US-FL' => new SellerActivity(
            150_000, 400,
            basis: NexusSalesBasis::TaxableSales,
            period: NexusMeasurementPeriod::PreviousCalendarYear,
        )],
    );

    $evaluation = $engine->evaluate(state('US-FL'));

    expect($evaluation->status)->toBe(NexusStatus::Triggered)
        ->and($evaluation->caveats)->toBe([])
        ->and($evaluation->isQualified())->toBeFalse();
});

it('qualifies a verdict the host never declared a basis for', function () {
    // Not an error the engine can detect — but a seller reading "register" deserves
    // to know that which sales it rests on was never verified.
    $threshold = new EconomicNexusThreshold(
        100_000, null, NexusCombinator::SalesOnly,
        measuringPeriod: NexusMeasurementPeriod::PreviousCalendarYear,
        salesBasis: NexusSalesBasis::TaxableSales,
    );

    $engine = $this->nexusEngine(
        thresholds: ['US-FL' => $threshold],
        activity: ['US-FL' => new SellerActivity(150_000, 400)],
    );

    $evaluation = $engine->evaluate(state('US-FL'));

    expect($evaluation->status)->toBe(NexusStatus::Triggered)
        ->and($evaluation->isQualified())->toBeTrue()
        ->and($evaluation->caveats)->toHaveCount(2);
});

// ---- "No sales tax here" is an answer, not a failure to find one ---------

it('reports a no-sales-tax state as not applicable, not unknown', function () {
    // Delaware, Montana, New Hampshire and Oregon have no threshold because there
    // is nothing to cross. Reporting them as Unknown puts four standing action
    // items on every healthy install and teaches operators to ignore the bucket.
    $engine = $this->nexusEngine(noSalesTax: ['US-DE', 'US-MT', 'US-NH', 'US-OR']);

    $evaluation = $engine->evaluate(state('US-DE'));

    expect($evaluation->status)->toBe(NexusStatus::NotApplicable)
        ->and($evaluation->needsAction())->toBeFalse()
        ->and($evaluation->reason)->toContain('no general sales tax');
});

it('still reports unknown for a state that is merely missing from the source', function () {
    // The distinction that matters: an absent key is not a statement about the
    // state, it is a statement about the data.
    $engine = $this->nexusEngine(noSalesTax: ['US-DE']);

    expect($engine->evaluate(state('US-CA'))->status)->toBe(NexusStatus::Unknown);
});

it('separates not-applicable from unknown in the report and rolls up what needs action', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-TX' => $this->salesThreshold(500_000)],
        activity: ['US-TX' => new SellerActivity(600_000, 900)],
        noSalesTax: ['US-DE'],
    );

    $report = $engine->report([state('US-TX'), state('US-DE'), state('US-CA')]);

    expect($report->triggered())->toHaveCount(1)
        ->and($report->notApplicable())->toHaveCount(1)
        ->and($report->unknown())->toHaveCount(1)
        // The roll-up a dashboard should ask for: the crossed obligation AND the
        // state it could not evaluate, never the one with nothing to comply with.
        ->and($report->needingAction())->toHaveCount(2);
});

it('qualifies a verdict where the dataset itself carries no basis', function () {
    $engine = $this->nexusEngine(
        thresholds: ['US-PA' => $this->salesThreshold(100_000)],
        activity: ['US-PA' => new SellerActivity(150_000, 400)],
    );

    expect($engine->evaluate(state('US-PA'))->isQualified())->toBeTrue();
});

it('honours the combinator for sales-or-transactions and sales-and-transactions', function () {
    $or = new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesOrTransactions);
    $and = new EconomicNexusThreshold(100_000, 200, NexusCombinator::SalesAndTransactions);

    $engine = $this->nexusEngine(
        thresholds: ['US-KY' => $or, 'US-CT' => $and],
        activity: ['US-KY' => $this->activity(5_000, 250), 'US-CT' => $this->activity(5_000, 250)],
    );

    // OR: 250 transactions alone triggers. AND: needs BOTH — $5k sales fails.
    expect($engine->evaluate(state('US-KY'))->status)->toBe(NexusStatus::Triggered)
        ->and($engine->evaluate(state('US-CT'))->status)->toBe(NexusStatus::Below);
});

it('rolls a report up across states', function () {
    $engine = $this->nexusEngine(
        thresholds: [
            'US-CA' => $this->salesThreshold(500_000),
            'US-TX' => $this->salesThreshold(500_000),
            'US-NY' => $this->salesThreshold(500_000),
        ],
        activity: [
            'US-CA' => $this->activity(600_000), // triggered
            'US-TX' => $this->activity(450_000), // approaching (0.9)
            'US-NY' => $this->activity(600_000), // registered
        ],
        registered: ['US-NY'],
    );

    $report = $engine->report([state('US-CA'), state('US-TX'), state('US-NY')]);

    expect($report->triggered())->toHaveCount(1)
        ->and($report->approaching())->toHaveCount(1)
        ->and($report->registered())->toHaveCount(1)
        ->and($report->forState('US-CA')?->status)->toBe(NexusStatus::Triggered);
});

it('does not tell a seller to register in a state that levies no sales tax', function () {
    // A warehouse in Oregon is real physical presence, and physical presence really
    // does establish nexus — but nexus to WHAT? Oregon levies no general sales tax,
    // so there is no sales-tax registration to take out. The engine checked physical
    // presence before it checked whether the state taxes at all, so this returned
    // Triggered with the words "establishes nexus — register".
    //
    // That is the expensive direction of wrong. A seller acting on it registers for
    // a tax that does not exist, and inherits a filing obligation — returns, due
    // dates, penalties for missing them — in return for nothing.
    $engine = $this->nexusEngine(
        physical: ['US-OR'],
        noSalesTax: ['US-OR'],
    );

    $evaluation = $engine->evaluate(new SubdivisionCode('US-OR'));

    expect($evaluation->status)->toBe(NexusStatus::NotApplicable)
        ->and($evaluation->reason)->not->toContain('register')
        // The presence itself is still reported — it is true, and it may matter for
        // taxes this package does not model (Oregon's own CAT, income tax nexus).
        ->and($evaluation->physicalPresence)->toBeTrue();
});

it('still honours an explicit registration in a no-sales-tax state', function () {
    // A host asserting a registration is stating a fact about itself, and the engine
    // does not overrule it with dataset knowledge — it just never INFERS one.
    $engine = $this->nexusEngine(registered: ['US-OR'], noSalesTax: ['US-OR']);

    expect($engine->evaluate(new SubdivisionCode('US-OR'))->status)->toBe(NexusStatus::Registered);
});
