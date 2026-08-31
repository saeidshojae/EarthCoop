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
                --bahar-coin-depth-scale: 1;
                transform: translate(-50%, -48%);
            }

            .bahar-coin--hero .bahar-coin__scene {
                transform-style: preserve-3d;
                -webkit-transform-style: preserve-3d;
                transform: scaleZ(var(--bahar-coin-depth-scale));
            }

            @media (max-width: 767px) {
                .bahar-coin--hero {
                    --bahar-coin-depth-scale: 0.64;
                    transform: none;
                }
            }
        </style>
    @endonce
@endif
