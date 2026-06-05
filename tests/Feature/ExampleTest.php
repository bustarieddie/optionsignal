<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path redirects to the dashboard (which redirects guests to login).
     */
    public function test_the_root_redirects(): void
    {
        $this->get('/')->assertRedirect();
    }
}
