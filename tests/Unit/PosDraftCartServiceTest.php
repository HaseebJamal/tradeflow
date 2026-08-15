<?php

namespace Tests\Unit;

use App\Services\PosDraftCartService;
use Tests\TestCase;

class PosDraftCartServiceTest extends TestCase
{
    public function test_newer_empty_sync_cannot_be_overwritten_by_an_older_cart_sync(): void
    {
        $session = $this->app['session']->driver();
        $session->start();
        $drafts = app(PosDraftCartService::class);

        $this->assertTrue($drafts->sync($session, 10, 20, 30, [['id' => 99]], 4));
        $this->assertTrue($drafts->sync($session, 10, 20, 30, [], 5));
        $this->assertFalse($drafts->sync($session, 10, 20, 30, [['id' => 99]], 4));
        $this->assertFalse($drafts->hasItems($session, 10, 20, 30));
    }

    public function test_clear_retains_an_empty_newer_generation(): void
    {
        $session = $this->app['session']->driver();
        $session->start();
        $drafts = app(PosDraftCartService::class);

        $drafts->sync($session, 11, 21, 31, [['id' => 88]], 8);
        $drafts->clear($session, 11, 21, 31);

        $this->assertFalse($drafts->hasItems($session, 11, 21, 31));
        $this->assertSame(9, $drafts->generation($session, 11, 21, 31));
        $this->assertFalse($drafts->sync($session, 11, 21, 31, [['id' => 88]], 8));
    }
}
