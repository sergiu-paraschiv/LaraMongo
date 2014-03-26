<?php namespace SergiuParaschiv\LaraMongo;

use Illuminate\Support\ServiceProvider;

class LaraMongoServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('sergiu-paraschiv/lara-mongo');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['db']->extend('mongodb', function($config)
        {
            return new Connection($config);
        });
    }
}
