<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-argent leading-tight">
            {{ __('Notifications') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if ($notifications->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    Aucune notification pour le moment.
                </div>
            @else
                @foreach ($notifications as $notification)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5 flex items-start justify-between gap-4
                                {{ $notification->lu ? 'opacity-60' : 'border-l-4 border-indigo-500' }}">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                @if (! $notification->lu)
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                @endif
                                <span class="text-xs text-gray-400">
                                    {{ $notification->date_envoi->diffForHumans() }}
                                </span>
                                @if ($notification->canal === 'sms')
                                    <span class="text-xs bg-teal-100 text-teal-800 px-2 py-0.5 rounded-full">📱 SMS</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-800">{{ $notification->message }}</p>

                            @if ($notification->declaration)
                                <a href="{{ route('declarations.show', $notification->declaration) }}"
                                   class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
                                    Voir la déclaration #{{ $notification->declaration->id }} →
                                </a>
                            @endif
                        </div>

                        @if (! $notification->lu)
                            <form method="POST" action="{{ route('notifications.marquer-lue', $notification) }}">
                                @csrf
                                <button type="submit" class="text-xs text-gray-400 hover:text-gray-700 whitespace-nowrap">
                                    Marquer comme lue
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach

                <div class="mt-4">
                    {{ $notifications->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>