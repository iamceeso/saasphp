<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class EnsureTwoFactorEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_request_when_two_factor_feature_is_enabled(): void
    {
        Setting::updateOrCreate(
            ['key' => 'features.enable_two_factor_auth'],
            ['value' => 'true', 'type' => 'boolean', 'group' => 'features']
        );

        $response = app(EnsureTwoFactorEnabled::class)->handle(
            Request::create('/two-factor-challenge', 'GET'),
            fn () => response('allowed')
        );

        $this->assertSame('allowed', $response->getContent());
    }

    public function test_aborts_when_two_factor_feature_is_disabled(): void
    {
        Setting::updateOrCreate(
            ['key' => 'features.enable_two_factor_auth'],
            ['value' => 'false', 'type' => 'boolean', 'group' => 'features']
        );

        $this->expectException(NotFoundHttpException::class);

        app(EnsureTwoFactorEnabled::class)->handle(
            Request::create('/two-factor-challenge', 'GET'),
            fn () => response('allowed')
        );
    }
}
