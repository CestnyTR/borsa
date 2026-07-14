<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guest_root_redirects(): void
    {
        // "/" panele yönlendirir; misafir oradan girişe düşer.
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
