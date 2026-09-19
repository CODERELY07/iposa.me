<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn bg-loss-600 text-white hover:bg-loss-500 active:bg-loss-700']) }}>
    {{ $slot }}
</button>
