<?php

namespace Metrique\Pagoti\Resources\Concerns;

trait CanFetchFresh
{
    private bool $fresh = false;

    public function fresh(): static
    {
        $this->fresh = true;

        return $this;
    }
}
