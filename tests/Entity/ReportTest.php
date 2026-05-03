<?php

namespace App\Tests\Entity;

use App\Entity\Report;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    public function testReportSettersAndGetters(): void
    {
        $report = new Report();

        $report->setType('POST');
        $report->setTargetId(10);
        $report->setReporterId(3);
        $report->setReason('Bad content');
        $report->setStatus('OPEN');

        self::assertSame('POST', $report->getType());
        self::assertSame(10, $report->getTargetId());
        self::assertSame(3, $report->getReporterId());
        self::assertSame('Bad content', $report->getReason());
        self::assertSame('OPEN', $report->getStatus());
    }
}