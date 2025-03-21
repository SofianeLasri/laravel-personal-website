<?php

namespace Tests\Feature\Models\Picture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PictureControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
