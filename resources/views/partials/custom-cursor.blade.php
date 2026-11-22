@if(\App\Facades\Settings::get('brand.custom_paw_cursor', false))
    @php
        $svgCursorUrl = \App\Services\MediaManager::cursorUrl('svg');
        $pngCursorUrl = \App\Services\MediaManager::cursorUrl('png');
    @endphp
    <style id="pawhaven-custom-cursor">
        /* Custom Paw Cursor — Only applied on desktop devices with hover & fine pointer */
        @media (hover: hover) and (pointer: fine) {
            html, body {
                cursor: url('{{ $svgCursorUrl }}') 5 5, url('{{ $pngCursorUrl }}') 5 5, auto;
            }

            /* Generic container elements inherit the paw cursor */
            div, section, article, nav, header, footer, main, aside, ul, ol, li {
                cursor: inherit;
            }

            /* Clickable links, buttons, and controls */
            a, button, [role="button"], input[type="submit"], input[type="button"], input[type="reset"], select, summary, .btn, .chip {
                cursor: url('{{ $svgCursorUrl }}') 5 5, url('{{ $pngCursorUrl }}') 5 5, pointer !important;
            }

            /* Text selection and readable content elements preserve native text cursor */
            p, h1, h2, h3, h4, h5, h6, label, blockquote, pre, code, [contenteditable="true"] {
                cursor: text;
            }

            /* Form text inputs, textareas, and search boxes preserve native text cursor */
            input[type="text"],
            input[type="email"],
            input[type="password"],
            input[type="search"],
            input[type="tel"],
            input[type="number"],
            input[type="url"],
            textarea {
                cursor: text !important;
            }

            /* Disabled controls preserve native not-allowed cursor */
            [disabled],
            .disabled,
            [aria-disabled="true"] {
                cursor: not-allowed !important;
            }
        }

        /* Respect accessibility reduced-motion preference */
        @media (prefers-reduced-motion: reduce) {
            /* Retain clean standard accessibility without decorative hover animations */
        }
    </style>
@endif
