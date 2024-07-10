<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function handleAbsences()
    {
        return response()->json(['message' => 'Absence handling and notifications triggered.']);
    }
}
