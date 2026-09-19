<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaStatusCommandTest extends TestCase
{
    public function test_diagnostic_accepts_system_user_token_without_exposing_secrets(): void
    {
        config()->set('services.facebook.client_id', 'app-test');
        config()->set('services.facebook.client_secret', 'secret-test');
        config()->set('services.meta.page_id', 'page-test');
        config()->set('services.meta.page_access_token', 'token-test');

        Http::fake([
            'https://graph.facebook.com/v26.0/page-test*' => Http::response(['id' => 'page-test']),
            'https://graph.facebook.com/v26.0/debug_token*' => Http::response([
                'data' => ['is_valid' => true, 'type' => 'SYSTEM_USER', 'expires_at' => 0],
            ]),
        ]);

        $this->assertSame(0, Artisan::call('spotlight:meta-status'));
        $output = Artisan::output();
        $this->assertStringContainsString('SYSTEM_USER', $output);
        $this->assertStringNotContainsString('token-test', $output);
        $this->assertStringNotContainsString('secret-test', $output);
        Http::assertSentCount(2);
    }

    public function test_diagnostic_reports_expired_token_without_showing_it(): void
    {
        config()->set('services.facebook.client_id', null);
        config()->set('services.facebook.client_secret', null);
        config()->set('services.meta.page_id', 'page-test');
        config()->set('services.meta.page_access_token', 'expired-token-test');
        Http::fake([
            '*' => Http::response(['error' => ['code' => 190]], 401),
        ]);

        $this->assertSame(1, Artisan::call('spotlight:meta-status'));
        $output = Artisan::output();
        $this->assertStringContainsString('code 190', $output);
        $this->assertStringNotContainsString('expired-token-test', $output);
        Http::assertSentCount(1);
    }
}
