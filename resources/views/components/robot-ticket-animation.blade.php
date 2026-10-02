<svg viewBox="0 0 520 420" xmlns="http://www.w3.org/2000/svg" {{ $attributes->merge(['class' => 'w-full max-w-lg']) }} role="img" aria-label="Un robot lance des tickets dans un laptop">
    <!-- Ombre au sol -->
    <ellipse cx="260" cy="388" rx="220" ry="14" fill="#000" opacity=".12"/>

    <!-- ========== LAPTOP ========== -->
    <g transform="translate(300,225)">
        <rect x="0" y="0" width="200" height="130" rx="10" fill="#1e293b"/>
        <rect x="10" y="10" width="180" height="110" rx="5" fill="#0f172a"/>
        <rect x="24" y="28" width="90" height="8" rx="4" fill="#6366f1" opacity=".8"/>
        <rect x="24" y="48" width="130" height="8" rx="4" fill="#334155"/>
        <rect x="24" y="68" width="110" height="8" rx="4" fill="#334155"/>
        <rect x="24" y="88" width="70" height="8" rx="4" fill="#334155"/>
        <!-- flash à chaque réception -->
        <rect x="10" y="10" width="180" height="110" rx="5" fill="#818cf8" opacity="0">
            <animate attributeName="opacity" values="0;0.35;0" keyTimes="0;0.15;1" dur="1.2s" begin="0.9s" repeatCount="indefinite"/>
        </rect>
        <!-- badge de notification -->
        <g transform="translate(168,22)">
            <circle r="11" fill="#22c55e">
                <animate attributeName="r" values="11;14;11" dur="1.2s" begin="0.9s" repeatCount="indefinite"/>
            </circle>
            <path d="M-5 0l4 4 7-8" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </g>
        <!-- base -->
        <path d="M-20 130h240l-14 18a8 8 0 0 1-6 3H0a8 8 0 0 1-6-3z" fill="#94a3b8"/>
        <rect x="80" y="130" width="40" height="6" rx="3" fill="#64748b"/>
    </g>

    <!-- ========== ROBOT ========== -->
    <g>
        <animateTransform attributeName="transform" type="translate" values="0,0;0,-8;0,0" dur="2.4s" repeatCount="indefinite"/>

        <!-- antenne -->
        <line x1="110" y1="95" x2="110" y2="70" stroke="#64748b" stroke-width="4" stroke-linecap="round"/>
        <circle cx="110" cy="64" r="8" fill="#f43f5e">
            <animate attributeName="opacity" values="1;.3;1" dur="1s" repeatCount="indefinite"/>
        </circle>

        <!-- tête -->
        <rect x="65" y="95" width="90" height="70" rx="20" fill="#6366f1"/>
        <rect x="75" y="108" width="70" height="42" rx="14" fill="#0f172a"/>
        <g fill="#67e8f9">
            <ellipse cx="97" cy="129" rx="7" ry="9">
                <animate attributeName="ry" values="9;9;1;9;9" keyTimes="0;.45;.5;.55;1" dur="3.6s" repeatCount="indefinite"/>
            </ellipse>
            <ellipse cx="123" cy="129" rx="7" ry="9">
                <animate attributeName="ry" values="9;9;1;9;9" keyTimes="0;.45;.5;.55;1" dur="3.6s" repeatCount="indefinite"/>
            </ellipse>
        </g>

        <!-- corps -->
        <rect x="75" y="172" width="70" height="90" rx="18" fill="#818cf8"/>
        <rect x="92" y="190" width="36" height="24" rx="6" fill="#4f46e5"/>
        <circle cx="102" cy="202" r="4" fill="#fde047"/>
        <circle cx="118" cy="202" r="4" fill="#4ade80"/>

        <!-- jambes -->
        <rect x="88" y="262" width="14" height="30" rx="6" fill="#4f46e5"/>
        <rect x="118" y="262" width="14" height="30" rx="6" fill="#4f46e5"/>

        <!-- bras gauche -->
        <rect x="48" y="182" width="22" height="14" rx="7" fill="#4f46e5"/>

        <!-- bras droit (lance les tickets) -->
        <g transform="translate(145,185)">
            <g>
                <animateTransform attributeName="transform" type="rotate"
                                  values="-10;-55;-10" keyTimes="0;.35;1" dur="1.2s" repeatCount="indefinite"
                                  calcMode="spline" keySplines=".4 0 .2 1;.4 0 .2 1"/>
                <rect x="-6" y="-7" width="50" height="14" rx="7" fill="#4f46e5"/>
                <circle cx="44" cy="0" r="9" fill="#a5b4fc"/>
            </g>
        </g>
    </g>

    <!-- ========== TICKETS ========== -->
    @foreach ([0, 0.4, 0.8] as $delay)
        <g transform="translate(205,175)" opacity="0">
            <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;.08;.85;1" dur="1.2s" begin="{{ $delay }}s" repeatCount="indefinite"/>
            <animateMotion dur="1.2s" begin="{{ $delay }}s" repeatCount="indefinite"
                           path="M0,0 Q 60,-110 160,70" calcMode="spline" keyTimes="0;1" keySplines=".3 .1 .6 1"/>
            <g>
                <animateTransform attributeName="transform" type="scale" values="1;.45" dur="1.2s" begin="{{ $delay }}s" repeatCount="indefinite"/>
                <g>
                    <animateTransform attributeName="transform" type="rotate" values="-15;380" dur="1.2s" begin="{{ $delay }}s" repeatCount="indefinite"/>
                    <rect x="-18" y="-11" width="36" height="22" rx="4" fill="#fbbf24"/>
                    <circle cx="-18" cy="0" r="4" fill="#f8fafc"/>
                    <circle cx="18" cy="0" r="4" fill="#f8fafc"/>
                    <line x1="-6" y1="-11" x2="-6" y2="11" stroke="#f59e0b" stroke-width="1.5" stroke-dasharray="3 2"/>
                    <rect x="0" y="-5" width="12" height="3" rx="1.5" fill="#b45309"/>
                    <rect x="0" y="2" width="8" height="3" rx="1.5" fill="#b45309"/>
                </g>
            </g>
        </g>
    @endforeach
</svg>
