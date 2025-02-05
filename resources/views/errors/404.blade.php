@extends('errors.layout')

@section('title', 'الصفحة غير موجودة')

@section('code', '404')

@section('icon')
    <div class="mx-auto w-24 h-24 rounded-full bg-red-50 flex items-center justify-center">
        <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </div>
@endsection

@section('message')
    <p class="mb-4">عذراً، الصفحة التي تبحث عنها غير موجودة أو تم نقلها.</p>
    <p class="text-gray-500">يمكنك العودة للصفحة الرئيسية أو الرجوع للصفحة السابقة.</p>
@endsection
