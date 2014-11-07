<?php

namespace Enjoin;

use Illuminate\Support\ServiceProvider;

class EnjoinServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->bind('enjoin', function () {
            return new Main;
        });
    }

} // end of class
