<?php

namespace App\Traits;


trait GoToIndex
{
    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
