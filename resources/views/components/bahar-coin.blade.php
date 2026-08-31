@props(['variant' => 'default'])

<figure {{ $attributes->class(['bahar-coin', 'bahar-coin--hero' => $variant === 'hero']) }} aria-hidden="true">
    <div class="bahar-coin__float">
        <div class="bahar-coin__scene">
            <div class="bahar-coin__rotor">
                <div class="bahar-coin__edge" style="--bahar-coin-mask: url('{{ asset('images/bahar/coin-front.webp') }}')">
                    @for ($layer = 0; $layer < 17; $layer++)
                        <span class="bahar-coin__edge-layer"></span>
                    @endfor
                </div>
                <img class="bahar-coin__face bahar-coin__face--front"
                     src="{{ asset('images/bahar/coin-front.webp') }}" width="1254" height="1254"
                     alt="" loading="eager" decoding="async" draggable="false">
                <img class="bahar-coin__face bahar-coin__face--back"
                     src="{{ asset('images/bahar/coin-back.webp') }}" width="1254" height="1254"
                     alt="" loading="eager" decoding="async" draggable="false">
            </div>
        </div>
    </div>
    <span class="bahar-coin__shadow"></span>
</figure>

@if ($variant === 'hero')
    @once
        <style>
            .bahar-coin--hero {
                --bahar-coin-edge-step: 1px;
                --bahar-coin-face-depth: 9px;
                transform: translate(-50%, -48%);
            }

            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(1) { transform: translateZ(calc(-8 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(2) { transform: translateZ(calc(-7 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(3) { transform: translateZ(calc(-6 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(4) { transform: translateZ(calc(-5 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(5) { transform: translateZ(calc(-4 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(6) { transform: translateZ(calc(-3 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(7) { transform: translateZ(calc(-2 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(8) { transform: translateZ(calc(-1 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(9) { transform: translateZ(0); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(10) { transform: translateZ(calc(1 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(11) { transform: translateZ(calc(2 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(12) { transform: translateZ(calc(3 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(13) { transform: translateZ(calc(4 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(14) { transform: translateZ(calc(5 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(15) { transform: translateZ(calc(6 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(16) { transform: translateZ(calc(7 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__edge-layer:nth-child(17) { transform: translateZ(calc(8 * var(--bahar-coin-edge-step))); }
            .bahar-coin--hero .bahar-coin__face--front { transform: translateZ(var(--bahar-coin-face-depth)); }
            .bahar-coin--hero .bahar-coin__face--back { transform: rotateY(180deg) translateZ(var(--bahar-coin-face-depth)) scale(0.95); }

            @media (max-width: 767px) {
                .bahar-coin--hero {
                    --bahar-coin-edge-step: 0.22px;
                    --bahar-coin-face-depth: 2px;
                    transform: none;
                }
            }
        </style>
    @endonce
@endif
