@extends('errors.layout')

@section('title', 'غير مصرح بالوصول')

@section('code', '403')

@section('icon')
    <div class="mx-auto w-24 h-24 rounded-full bg-yellow-50 flex items-center justify-center">
        <svg class="w-12 h-12 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 15v2m0 0v2m0-2h2m-2 0H10m4 0h6m-6 0l2-2m-2 2l2 2M6 21h12a2 2 0 002-2V5a2 2 0 00-2-2H6a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
    </div>
@endsection

@section('message')
    <p class="mb-4">عذراً، لا تملك الصلاحيات الكافية للوصول إلى هذه الصفحة.</p>
    <p class="text-gray-500">إذا كنت تعتقد أن هذا خطأ، يرجى التواصل مع مسؤول النظام.</p>
@endsection
