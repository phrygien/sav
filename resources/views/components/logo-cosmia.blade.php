<svg {{ $attributes->merge(['class' => 'h-8 w-auto']) }} viewBox="0 0 66 48" xmlns="http://www.w3.org/2000/svg" aria-label="Robot CosmIA SAV qui lance un ticket dans un laptop">

    <!-- Ombre au sol -->
    <ellipse cx="16" cy="45" rx="9" ry="1.6" fill="currentColor" opacity=".15">
        <animate attributeName="rx" values="9;7.5;9" dur="1.5s" repeatCount="indefinite"/>
    </ellipse>

    <!-- ===== LAPTOP ===== -->
    <g>
        <rect x="41" y="15" width="22" height="16" rx="2.5" fill="var(--color-accent, #6366f1)"/>
        <rect x="43.5" y="17.5" width="17" height="11" rx="1.5" fill="#1e293b"/>
        <path d="M37 33 h30 a1.5 1.5 0 0 1 0 3 h-30 a1.5 1.5 0 0 1 0 -3z" fill="var(--color-accent, #6366f1)" opacity=".85"/>
        <!-- Fente d'entrée lumineuse -->
        <rect x="46" y="17.5" width="12" height="1.2" rx=".6" fill="#fbbf24">
            <animate attributeName="opacity" values="0;0;1;0;0" keyTimes="0;.62;.7;.8;1" dur="3s" repeatCount="indefinite"/>
        </rect>
        <!-- Coche de validation -->
        <path d="M48.5 23.5 l3 3 l6 -6" stroke="#22c55e" stroke-width="1.8" fill="none"
              stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="14">
            <animate attributeName="stroke-dashoffset" values="14;14;0;0;14" keyTimes="0;.72;.82;.96;1" dur="3s" repeatCount="indefinite"/>
        </path>
        <!-- Étincelle -->
        <path d="M63 12 l1 2.5 l2.5 1 l-2.5 1 l-1 2.5 l-1 -2.5 l-2.5 -1 l2.5 -1z" fill="#fbbf24">
            <animate attributeName="opacity" values="0;0;1;0;0" keyTimes="0;.74;.82;.92;1" dur="3s" repeatCount="indefinite"/>
        </path>
    </g>

    <!-- ===== ROBOT (rebond) ===== -->
    <g>
        <animateTransform attributeName="transform" type="translate"
                          values="0 0;0 -1.6;0 0" dur="1.5s" repeatCount="indefinite"/>

        <!-- Antenne + cœur -->
        <line x1="16" y1="8" x2="16" y2="4.5" stroke="var(--color-accent, #6366f1)" stroke-width="1.8" stroke-linecap="round"/>
        <circle cx="16" cy="3.4" r="2.2" fill="#f472b6">
            <animate attributeName="r" values="2.2;2.8;2.2" dur="1s" repeatCount="indefinite"/>
        </circle>

        <!-- Oreilles -->
        <circle cx="5" cy="16" r="2.4" fill="var(--color-accent, #6366f1)"/>
        <circle cx="27" cy="16" r="2.4" fill="var(--color-accent, #6366f1)"/>

        <!-- Tête ronde -->
        <rect x="6" y="8" width="20" height="17" rx="8.5" fill="var(--color-accent, #6366f1)"/>
        <!-- Visage -->
        <rect x="8.5" y="10.5" width="15" height="12" rx="6" fill="#ffffff"/>
        <!-- Yeux (clignent) -->
        <g fill="#1e293b">
            <ellipse cx="12.8" cy="16" rx="1.7" ry="2.1">
                <animate attributeName="ry" values="2.1;2.1;.2;2.1;2.1" keyTimes="0;.45;.5;.55;1" dur="3s" repeatCount="indefinite"/>
            </ellipse>
            <ellipse cx="19.2" cy="16" rx="1.7" ry="2.1">
                <animate attributeName="ry" values="2.1;2.1;.2;2.1;2.1" keyTimes="0;.45;.5;.55;1" dur="3s" repeatCount="indefinite"/>
            </ellipse>
        </g>
        <circle cx="13.4" cy="15.2" r=".6" fill="#fff"/>
        <circle cx="19.8" cy="15.2" r=".6" fill="#fff"/>
        <!-- Joues + sourire -->
        <circle cx="10.4" cy="19.6" r="1.4" fill="#f9a8d4" opacity=".8"/>
        <circle cx="21.6" cy="19.6" r="1.4" fill="#f9a8d4" opacity=".8"/>
        <path d="M14.2 19.6 Q16 21.4 17.8 19.6" stroke="#1e293b" stroke-width="1.1" fill="none" stroke-linecap="round"/>

        <!-- Corps -->
        <rect x="9" y="26.5" width="14" height="11" rx="5" fill="var(--color-accent, #6366f1)"/>
        <path d="M16 34.6 c-2.6 -1.6 -3.2 -3.2 -2 -4.2 c.8 -.7 1.6 -.3 2 .4 c.4 -.7 1.2 -1.1 2 -.4 c1.2 1 .6 2.6 -2 4.2z" fill="#f472b6"/>
        <!-- Pieds -->
        <ellipse cx="12.5" cy="39" rx="3" ry="1.8" fill="currentColor" opacity=".7"/>
        <ellipse cx="19.5" cy="39" rx="3" ry="1.8" fill="currentColor" opacity=".7"/>

        <!-- Bras gauche -->
        <ellipse cx="7.6" cy="32" rx="1.8" ry="3.2" fill="var(--color-accent, #6366f1)" transform="rotate(12 7.6 32)"/>

        <!-- Bras droit (lance) -->
        <g>
            <animateTransform attributeName="transform" type="rotate"
                              values="0 23 30;-45 23 30;-45 23 30;20 23 30;0 23 30"
                              keyTimes="0;.15;.28;.42;1" dur="3s" repeatCount="indefinite"/>
            <rect x="22" y="28.4" width="8" height="3.2" rx="1.6" fill="var(--color-accent, #6366f1)"/>
            <circle cx="30.2" cy="30" r="2" fill="#fde68a"/>
        </g>

        <!-- Ticket lancé -->
        <g>
            <animateTransform attributeName="transform" type="translate"
                              values="0 0;0 0;4 -10;11 -11;16 -4;16 -4"
                              keyTimes="0;.28;.4;.52;.66;1" dur="3s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values="0;1;1;0;0" keyTimes="0;.08;.64;.68;1" dur="3s" repeatCount="indefinite"/>
            <rect x="31" y="22" width="8" height="6.5" rx="1.2" fill="#fbbf24"/>
            <circle cx="31" cy="25.2" r="1" fill="var(--color-bg, #fff)"/>
            <circle cx="39" cy="25.2" r="1" fill="var(--color-bg, #fff)"/>
            <line x1="33" y1="24" x2="37" y2="24" stroke="#92400e" stroke-width=".8" stroke-linecap="round"/>
            <line x1="33" y1="26.2" x2="36" y2="26.2" stroke="#92400e" stroke-width=".8" stroke-linecap="round"/>
        </g>
    </g>
</svg>
