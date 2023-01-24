<?php

namespace Oliverbj\Cord\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Oliverbj\Cord\Cord
 */
class Cord extends Facade
{
    protected static function getFacadeAccessor()
    {
        //return \Oliverbj\Cord\Cord::class;
        return 'cord';
    }
}
