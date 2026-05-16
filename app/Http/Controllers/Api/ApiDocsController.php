<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class ApiDocsController extends Controller
{
    public function index()
    {
        $specPath = base_path('docs/openapi.yaml');

        if (! File::exists($specPath)) {
            abort(404, 'OpenAPI specification not found.');
        }

        return inertia('ApiDocs', [
            'specUrl' => route('api.docs.spec'),
        ]);
    }

    public function spec()
    {
        $specPath = base_path('docs/openapi.yaml');

        if (! File::exists($specPath)) {
            abort(404, 'OpenAPI specification not found.');
        }

        return response()->file($specPath, [
            'Content-Type' => 'text/yaml',
        ]);
    }
}
