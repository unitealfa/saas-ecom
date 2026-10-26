<?php

test('the public home redirects to the Fortify login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
