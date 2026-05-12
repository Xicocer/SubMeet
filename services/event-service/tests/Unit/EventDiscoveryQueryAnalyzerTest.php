<?php

namespace Tests\Unit;

use App\Services\EventDiscoveryQueryAnalyzer;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class EventDiscoveryQueryAnalyzerTest extends TestCase
{
    public function test_it_detects_date_intent_and_next_week_timeframe(): void
    {
        CarbonImmutable::setTestNow('2026-05-02 12:00:00');

        $analysis = (new EventDiscoveryQueryAnalyzer)->analyze(
            'На следующей неделе планирую свидание, подскажи куда сходить'
        );

        $this->assertSame('date', $analysis['intent']);
        $this->assertSame('свидание', $analysis['intent_label']);
        $this->assertSame('на следующей неделе', $analysis['timeframe_label']);
        $this->assertContains('theater', $analysis['preferred_categories']);

        CarbonImmutable::setTestNow();
    }

    public function test_it_detects_friends_intent_for_weekend_queries(): void
    {
        CarbonImmutable::setTestNow('2026-05-02 12:00:00');

        $analysis = (new EventDiscoveryQueryAnalyzer)->analyze(
            'Хочу с друзьями на выходных что-то энергичное'
        );

        $this->assertSame('friends', $analysis['intent']);
        $this->assertSame('компания друзей', $analysis['intent_label']);
        $this->assertSame('на выходных', $analysis['timeframe_label']);
        $this->assertContains('festival', $analysis['preferred_categories']);

        CarbonImmutable::setTestNow();
    }
}
