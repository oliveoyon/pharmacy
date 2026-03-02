<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureBranchAccess;
use App\Http\Middleware\EnsurePlatformUser;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
            'tenant.branch' => EnsureBranchAccess::class,
            'platform.user' => EnsurePlatformUser::class,
            'set.locale' => SetLocale::class,
        ]);

        $middleware->appendToGroup('web', [
            SetLocale::class,
        ]);

        $middleware->appendToGroup('api', [
            ResolveTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
