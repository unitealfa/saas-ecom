<?php

test('the central entry directs guests to authentication', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
