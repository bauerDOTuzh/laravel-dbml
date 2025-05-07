<?php

namespace Bauerdot\LaravelDbml;

use Bauerdot\LaravelDbml\Console\DBML;
use Bauerdot\LaravelDbml\Console\DBMLParse;
use Illuminate\Support\ServiceProvider;

class LaravelDbmlServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        if($this->app->runningInConsole ()){
            $this->commands ([
                DBML::class,
                DBMLParse::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/laravel-dbml.php' => config_path('laravel-dbml.php'),
        ], 'config');
        
        // Merge with default config
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-dbml.php', 'laravel-dbml'
        );
    }
}
