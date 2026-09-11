<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class DeploymentConsoleController extends Controller
{
    public function index(): Response
    {
        abort(404);
    }
}
