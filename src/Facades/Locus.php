<?php

namespace Glanum\Locus\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Glanum\Locus\Locus
 */
class Locus extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'locus';
    }
}
