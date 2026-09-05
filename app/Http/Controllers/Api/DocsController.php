<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Contracts\View\View;

class DocsController extends Controller
{
    public function __invoke(): View
    {
        return view('api-docs');
    }
}
