<?php

test('returns a successful response', function () {
    $this->withoutVite();

    $response = $this->get('/');

    $response->assertStatus(200);
});
