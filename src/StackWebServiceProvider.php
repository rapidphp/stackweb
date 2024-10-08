<?php

namespace StackWeb;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class StackWebServiceProvider extends ServiceProvider
{

    public function register()
    {
        View::addExtension('stack.php', 'stack-web', function () {
            return new Engine\StackWebEngine();
        });

        Route::get('/stackweb.js', function () {
            return response()->file(__DIR__ . '/../dist/stackweb.js');
        })->name('stackweb.js');
    }

}