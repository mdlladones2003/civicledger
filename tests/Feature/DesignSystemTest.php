<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the public design system page', function () {
    $this->get(route('design-system'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('DesignSystem', false));
});
