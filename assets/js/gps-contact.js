/**
 * GPS Courses - contact page: "Chat with GIA" opens the GIA chat (WC Cart
 * Recovery Pro) by clicking its bubble. Without the chat, the link keeps
 * its href and scrolls to the form.
 */
(function () {
    'use strict';

    var btn = document.querySelector('.gps-ct-gia');
    if (!btn) { return; }

    btn.addEventListener('click', function (e) {
        var bubble = document.querySelector('.wcrp-ai-bubble');
        if (!bubble) { return; }
        e.preventDefault();
        bubble.click();
        // Delayed scripts may bind the bubble only after this first interaction
        window.setTimeout(function () {
            var chat = document.getElementById('wcrp-ai-chat');
            if (chat && !chat.classList.contains('open')) {
                bubble.click();
                // Still closed: send people to the form instead of doing nothing
                window.setTimeout(function () {
                    if (!chat.classList.contains('open')) { window.location.hash = 'message'; }
                }, 1200);
            }
        }, 1200);
    });
})();
