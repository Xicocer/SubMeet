<?php

namespace App\Providers;

use App\Services\Payments\MockPaymentGateway;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\YooKassaPaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $provider = (string) config('payments.provider', 'mock');

            return match ($provider) {
                'yookassa' => $app->make(YooKassaPaymentGateway::class),
                default => $app->make(MockPaymentGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
