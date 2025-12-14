<?php

namespace App\Services\GoldScrapers;

interface GoldScraperInterface
{
    /**
     * Fetch gold price from the source
     *
     * @return array ['buy' => float|null, 'sell' => float]
     */
    public function fetchPrice(): array;

    /**
     * Get the source code identifier
     *
     * @return string
     */
    public function getSourceCode(): string;
}
