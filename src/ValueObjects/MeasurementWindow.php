<?php

declare(strict_types=1);

namespace Cbox\Nexus\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A concrete date range a seller's totals must be accumulated over.
 *
 * Both bounds are INCLUSIVE of their day. A calendar year that ends on 31 December
 * has to contain the sales made on 31 December, and an exclusive end is the classic
 * way to lose them.
 */
readonly class MeasurementWindow
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        /** How to name it in a caveat or a dashboard — "2025", "rolling 12 months to 2026-08-13". */
        public string $label,
    ) {}

    public function covers(DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');

        return $this->from->format('Y-m-d') <= $day && $day <= $this->to->format('Y-m-d');
    }

    public function describe(): string
    {
        return $this->label;
    }
}
