@extends('errors.layout')

@section('title', 'خطأ في الخادم')

@section('code', '500')

@section('icon')
    <div class="mx-auto w-24 h-24 rounded-full bg-red-50 flex items-center justify-center">
        <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
    </div>
@endsection

@section('message')
    <p class="mb-4">عذراً، حدث خطأ غير متوقع في الخادم.</p>
    <p class="text-gray-500">نحن نعمل على حل المشكلة. يرجى المحاولة مرة أخرى لاحقاً.</p>
    <p class="text-sm text-gray-400 mt-2">إذا استمرت المشكلة، يرجى التواصل مع الدعم الفني.</p>
@endsection
