<?php

use App\Libraries\AvailabilityService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AvailabilityServiceTest extends CIUnitTestCase
{
    public function testRejectsPastDay()
    {
        $now = new DateTimeImmutable('2026-06-08 15:00:00', new DateTimeZone('America/Argentina/Buenos_Aires'));

        $this->assertTrue(AvailabilityService::isReservationInPast('2026-06-07', '15:00', $now));
    }

    public function testRejectsPastHourOnSameDay()
    {
        $now = new DateTimeImmutable('2026-06-08 15:00:00', new DateTimeZone('America/Argentina/Buenos_Aires'));

        $this->assertTrue(AvailabilityService::isReservationInPast('2026-06-08', '14:00', $now));
    }

    public function testAllowsFutureHourOnSameDay()
    {
        $now = new DateTimeImmutable('2026-06-08 15:00:00', new DateTimeZone('America/Argentina/Buenos_Aires'));

        $this->assertFalse(AvailabilityService::isReservationInPast('2026-06-08', '15:30', $now));
    }
}
