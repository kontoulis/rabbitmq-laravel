<?php

namespace Kontoulis\RabbitMQLaravel;

use Illuminate\Support\ServiceProvider;

/**
 * Class RabbitMQLaravelServiceProvider
 * @package Kontoulis\RabbitMQLaravel
 */
class RabbitMQLaravelServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
	    $this->publishes([
		    __DIR__.'/config.php' => base_path('config/rabbitmq-laravel.php'),
	    ]);


    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {

	    $this->app->bind('Kontoulis\RabbitMQLaravel\RabbitMQ', function ($app) {
			$config = $app['config']->get("rabbitmq-laravel");
		    return new RabbitMQ($config);
	    });
	    
    }
}
