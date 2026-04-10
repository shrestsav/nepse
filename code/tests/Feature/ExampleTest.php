<?php

use Inertia\Testing\AssertableInertia as Assert;

test('home renders the public blog page', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('blog/Index')
        ->has('posts')
    );
});
