<?php

namespace iEXPackages\Courses;

use Illuminate\Support\Facades\Facade;

class CoursesFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'courses';
    }
}
