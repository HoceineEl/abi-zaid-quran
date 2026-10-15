<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationImportFailuresController extends Controller
{
    public function show(string $file): StreamedResponse
    {
        $path = "registration-imports/failures/{$file}.xlsx";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'أسطر-غير-مستوردة.xlsx');
    }
}
