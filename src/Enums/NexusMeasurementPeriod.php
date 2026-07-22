<?php

declare(strict_types=1);

namespace Cbox\Nexus\Enums;

use Cbox\Nexus\ValueObjects\SellerActivity;

/**
 * The window a state measures the threshold over — mirrors us-tax-data. It tells
 * the host over which period to accumulate {@see SellerActivity}.
 */
enum NexusMeasurementPeriod: string
{
    case PreviousCalendarYear = 'previous_calendar_year';
    case CurrentCalendarYear = 'current_calendar_year';
    case PreviousOrCurrentCalendarYear = 'previous_or_current_calendar_year';
    case RollingTwelveMonths = 'rolling_twelve_months';
}
