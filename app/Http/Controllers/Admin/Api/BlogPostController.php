<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(BlogPost::all());
    }

    public function store(Request $request) {}

    public function show(BlogPost $blogPost) {}

    public function update(Request $request, BlogPost $blogPost) {}

    public function destroy(BlogPost $blogPost) {}
}
