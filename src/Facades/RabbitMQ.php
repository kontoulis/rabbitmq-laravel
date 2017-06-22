<?php

namespace Kontoulis\RabbitMQLaravel\Facades;

use Illuminate\Support\Facades\Facade;

class RabbitMQ extends Facade{

	protected static function getFacadeAccessor()
	{
		return 'Kontoulis\RabbitMQLaravel\RabbitMQ';
	}
} 