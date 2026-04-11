<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Gate;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Provide a stable authorize() entrypoint across controllers.
     */
    protected function authorize($ability, $arguments = [])
    {
        return Gate::authorize($ability, $arguments);
    }
}
