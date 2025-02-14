<div class="space-y-6">
    {{-- Attendance Status --}}
    <div class="p-4 bg-gray-50 rounded-lg">
        <div class="font-semibold text-lg mb-2">حالة الحضور</div>
        <div class="flex items-center gap-2">
            @if($attendance->check_in_time)
                <x-heroicon-s-check-circle class="w-5 h-5 text-success-500"/>
                <span>حاضر ({{ \Carbon\Carbon::parse($attendance->check_in_time)->format('H:i') }})</span>
            @else
                <x-heroicon-s-x-circle class="w-5 h-5 text-danger-500"/>
                <span>غائب</span>
            @endif
        </div>
    </div>

    {{-- Score --}}
    @if($attendance->score)
        <div class="p-4 bg-gray-50 rounded-lg">
            <div class="font-semibold text-lg mb-2">تقييم الحفظ</div>
            <div class="flex items-center gap-2">
                @php
                    $scoreColor = match($attendance->score) {
                        \App\Enums\MemorizationScore::EXCELLENT => 'emerald',
                        \App\Enums\MemorizationScore::VERY_GOOD => 'green',
                        \App\Enums\MemorizationScore::GOOD => 'blue',
                        \App\Enums\MemorizationScore::FAIR => 'amber',
                        \App\Enums\MemorizationScore::ACCEPTABLE => 'gray',
                        \App\Enums\MemorizationScore::POOR => 'red',
                        \App\Enums\MemorizationScore::NOT_MEMORIZED => 'rose',
                        default => 'gray'
                    };
                @endphp
                <span class="inline-flex items-center justify-center px-3 py-1 text-sm font-medium rounded-full bg-{{ $scoreColor }}-100 text-{{ $scoreColor }}-700">
                    {{ $attendance->score->getLabel() }}
                </span>
            </div>
        </div>
    @endif

    {{-- Custom Note --}}
    @if($attendance->custom_note)
        <div class="p-4 bg-gray-50 rounded-lg">
            <div class="font-semibold text-lg mb-2">ملاحظات خاصة</div>
            <p class="text-gray-700">{{ $attendance->custom_note }}</p>
        </div>
    @endif

    {{-- Behavioral Issues --}}
    @if($attendance->notes)
        @php
            $notes = $attendance->notes;
        @endphp
        @if(!empty($notes))
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="font-semibold text-lg mb-2">ملاحظات السلوك</div>
                <ul class="space-y-2">
                    @foreach($notes as $note)
                        @php
                            $trouble = App\Enums\Troubles::from($note);
                        @endphp
                        <li class="flex items-center gap-2">
                            <x-dynamic-component 
                                :component="'heroicon-o-' . str_replace('heroicon-o-', '', $trouble->getIcon())"
                                class="w-5 h-5 text-warning-500"
                            />
                            <span>{{ $trouble->getLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    {{-- Additional Information --}}
    <div class="p-4 bg-gray-50 rounded-lg">
        <div class="font-semibold text-lg mb-2">معلومات إضافية</div>
        <div class="text-sm text-gray-600">
            تم تسجيل هذه المعلومات في {{ \Carbon\Carbon::parse($date)->format('Y/m/d') }}
        </div>
    </div>
</div>