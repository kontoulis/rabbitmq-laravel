<?php

namespace Kontoulis\RabbitMQLaravel\Traits;

trait SingletonTrait {
    protected static $inst = null;

    /**
     * call this method to get instance
     **/
    public static function Instance($config = null){
        if (static::$inst === null){
            static::$inst = new self($config);
        }
        return static::$inst;
    }

    /**
     * protected to prevent clonning
     **/
    protected function __clone(){
    }

    /**
     * protected so no one else can instance it
     **/
    protected function __construct(){
    }
}