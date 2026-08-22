<?php

declare(strict_types=1);

namespace PhpAnonymizer\AnonymizerLaravel\Tests\Support\ServiceProvider;

use Illuminate\Support\ServiceProvider;
use Override;
use PhpAnonymizer\AnonymizerLaravel\Tests\Support\Profile\TestProfileProvider;

final class TestProfileProviderServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(TestProfileProvider::class);
        $this->app->tag(TestProfileProvider::class, 'anonymizer.profile_provider');
    }
}
