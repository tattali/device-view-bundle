<?php

declare(strict_types=1);

/*
 * Real user agents, with where each one comes from and the view each detector gives.
 *
 * - user_agent: the User-Agent header value; null means the request has no User-Agent header.
 * - sec_ch_ua_mobile: the Sec-CH-UA-Mobile header a Chromium browser sends by default with that
 *   user agent (Chromium 89 and later send it on every HTTPS request); null means no header. It
 *   is not captured from the device: it follows the Mobile token of the user agent.
 * - source and date: where the string comes from and when it was read there.
 * - expected: the view each detector of docs/design.md section 9 gives with its default
 *   configuration; null means the detector cannot tell, so default_view applies.
 *   The mobile_detect and matomo values were measured on 2026-10-20 with
 *   mobiledetect/mobiledetectlib 4.11.0 and matomo/device-detector 6.5.2, through the mapping of
 *   design.md section 9 (Mobile Detect: isTablet(), then isMobile(), else desktop, empty user
 *   agent null; Matomo: tablet, phablet as mobile, smartphone and feature phone mobile, bots and
 *   other known types desktop, unknown null). The client_hints values follow sec_ch_ua_mobile.
 */

$iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1';

return [
    'iphone' => [
        'user_agent' => $iphone,
        'sec_ch_ua_mobile' => null,
        'source' => 'MDN, User-Agent header reference, Safari on iPhone example (page last modified 2025-10-28): https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/User-Agent',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'mobile', 'matomo' => 'mobile', 'client_hints' => null],
    ],
    'ipad_legacy' => [
        'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11B554a Safari/9537.53',
        'sec_ch_ua_mobile' => null,
        'source' => 'matomo/device-detector 6.5.2, Tests/fixtures/tablet.yml: https://github.com/matomo-org/device-detector/blob/6.5.2/Tests/fixtures/tablet.yml',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'tablet', 'matomo' => 'tablet', 'client_hints' => null],
    ],
    'ipados_desktop' => [
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1 Safari/605.1.15',
        'sec_ch_ua_mobile' => null,
        'source' => 'MDN, Firefox user agent string reference, Safari 13.1 example (page last modified 2026-04-13): https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/User-Agent/Firefox',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'desktop', 'matomo' => 'desktop', 'client_hints' => null],
        'note' => 'Safari on iPadOS 13 and later sends the macOS Safari user agent by default (desktop-class browsing), so an iPad gets the desktop view. Documented limitation.',
    ],
    'android_phone_reduced' => [
        'user_agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36',
        'sec_ch_ua_mobile' => '?1',
        'source' => 'MDN, User-Agent header reference, Chrome on Android example (page last modified 2025-10-28): https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/User-Agent',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'mobile', 'matomo' => 'mobile', 'client_hints' => 'mobile'],
    ],
    'android_tablet_reduced' => [
        'user_agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.0.0 Safari/537.36',
        'sec_ch_ua_mobile' => '?0',
        'source' => 'Chromium, User-Agent Reduction, reduced Android tablet example: https://www.chromium.org/updates/ua-reduction/',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'mobile', 'matomo' => 'tablet', 'client_hints' => 'desktop'],
        'note' => 'The reduced user agent hides the device model. Mobile Detect sees Android without a tablet token and answers mobile; Matomo reads the missing Mobile token as a tablet; Client Hints never answers tablet.',
    ],
    'galaxy_tab' => [
        'user_agent' => 'Mozilla/5.0 (Linux; Android 9; SM-T810) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.1234.56 Safari/537.36',
        'sec_ch_ua_mobile' => '?0',
        'source' => 'Chromium, User-Agent Reduction, unreduced Galaxy Tab S2 (SM-T810) example: https://www.chromium.org/updates/ua-reduction/',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'tablet', 'matomo' => 'tablet', 'client_hints' => 'desktop'],
    ],
    'phablet' => [
        'user_agent' => 'Mozilla/5.0 (Linux; Android 10; SM-N770F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Mobile Safari/537.36',
        'sec_ch_ua_mobile' => '?1',
        'source' => 'matomo/device-detector 6.5.2, Tests/fixtures/phablet.yml (Galaxy Note10 Lite): https://github.com/matomo-org/device-detector/blob/6.5.2/Tests/fixtures/phablet.yml',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'mobile', 'matomo' => 'mobile', 'client_hints' => 'mobile'],
        'note' => 'Matomo reports a phablet; the matomo value follows detector.matomo.phablet_as (mobile by default, tablet when set so).',
    ],
    'kindle_silk' => [
        'user_agent' => 'Mozilla/5.0 (Linux; Android 4.4.3; KFTHWI Build/KTU84M) AppleWebKit/537.36 (KHTML, like Gecko) Silk/44.1.54 like Chrome/44.0.2403.63 Safari/537.36',
        'sec_ch_ua_mobile' => null,
        'source' => 'Amazon Silk Developer Guide, user agent string examples, tablet: https://docs.aws.amazon.com/silk/latest/developerguide/user-agent.html',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'tablet', 'matomo' => 'tablet', 'client_hints' => null],
    ],
    'desktop_chrome' => [
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
        'sec_ch_ua_mobile' => '?0',
        'source' => 'MDN, User-Agent header reference, Chrome on Windows example (page last modified 2025-10-28): https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/User-Agent',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'desktop', 'matomo' => 'desktop', 'client_hints' => 'desktop'],
    ],
    'desktop_firefox' => [
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:158.0) Gecko/20100101 Firefox/158.0',
        'sec_ch_ua_mobile' => null,
        'source' => 'Local run of Firefox 158.0 on macOS (headless, against a local PHP server that logged the header)',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'desktop', 'matomo' => 'desktop', 'client_hints' => null],
    ],
    'desktop_safari' => [
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15',
        'sec_ch_ua_mobile' => null,
        'source' => 'MDN, User-Agent header reference, Safari on macOS example (page last modified 2025-10-28): https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/User-Agent',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'desktop', 'matomo' => 'desktop', 'client_hints' => null],
    ],
    'desktop_edge' => [
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0',
        'sec_ch_ua_mobile' => '?0',
        'source' => 'MDN, User-Agent header reference, Edge on Windows example (page last modified 2025-10-28): https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/User-Agent',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'desktop', 'matomo' => 'desktop', 'client_hints' => 'desktop'],
    ],
    'googlebot_desktop' => [
        'user_agent' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/143.0.0.0 Safari/537.36',
        'sec_ch_ua_mobile' => null,
        'source' => 'Google Search Central, Google common crawlers, Googlebot Desktop (page last updated 2026-10-15; its Chrome/W.X.Y.Z placeholder set to 143.0.0.0 here): https://developers.google.com/search/docs/crawling-indexing/google-common-crawlers',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'desktop', 'matomo' => 'desktop', 'client_hints' => null],
    ],
    'googlebot_smartphone' => [
        'user_agent' => 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'sec_ch_ua_mobile' => null,
        'source' => 'Google Search Central, Google common crawlers, Googlebot Smartphone (page last updated 2026-10-15; its Chrome/W.X.Y.Z placeholder set to 143.0.0.0 here): https://developers.google.com/search/docs/crawling-indexing/google-common-crawlers',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'mobile', 'matomo' => 'desktop', 'client_hints' => null],
        'note' => 'Matomo reports every bot, so the bundle maps it to desktop; Mobile Detect reads the phone user agent.',
    ],
    'bingbot' => [
        'user_agent' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        'sec_ch_ua_mobile' => null,
        'source' => 'matomo/device-detector 6.5.2, Tests/fixtures/bots.yml: https://github.com/matomo-org/device-detector/blob/6.5.2/Tests/fixtures/bots.yml',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'desktop', 'matomo' => 'desktop', 'client_hints' => null],
    ],
    'curl' => [
        'user_agent' => 'curl/8.22.0',
        'sec_ch_ua_mobile' => null,
        'source' => 'Default user agent of a local run of curl 8.22.0 on macOS',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'desktop', 'matomo' => null, 'client_hints' => null],
    ],
    'empty' => [
        'user_agent' => '',
        'sec_ch_ua_mobile' => null,
        'source' => 'Edge case: an empty User-Agent header',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => null, 'matomo' => null, 'client_hints' => null],
    ],
    'missing' => [
        'user_agent' => null,
        'sec_ch_ua_mobile' => null,
        'source' => 'Edge case: no User-Agent header',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => null, 'matomo' => null, 'client_hints' => null],
    ],
    'long_2kb' => [
        'user_agent' => str_pad($iphone.' ', 2048, 'x'),
        'sec_ch_ua_mobile' => null,
        'source' => 'Edge case: the iphone user agent padded with "x" to 2048 bytes (Mobile Detect reads the first 500 bytes)',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'mobile', 'matomo' => 'mobile', 'client_hints' => null],
    ],
    'invalid_utf8' => [
        'user_agent' => $iphone." \xC3\x28\xFF",
        'sec_ch_ua_mobile' => null,
        'source' => 'Edge case: the iphone user agent followed by the invalid UTF-8 bytes C3 28 FF',
        'date' => '2026-10-20',
        'expected' => ['mobile_detect' => 'mobile', 'matomo' => 'mobile', 'client_hints' => null],
    ],
];
