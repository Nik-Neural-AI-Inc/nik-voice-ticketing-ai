/**
 * Nik VoiceDesk AI - Frontend Interactive Script
 * Zero-dependencies: Pure Vanilla JS
 */

document.addEventListener('DOMContentLoaded', function() {
    const micBtn = document.getElementById('nik-vd-mic-btn');
    const dock = document.getElementById('nik-vd-dock');
    const stopBtn = document.getElementById('nik-vd-stop-btn');
    const cancelBtn = document.getElementById('nik-vd-cancel-btn');
    const timerDisplay = document.getElementById('nik-vd-timer');
    const clickCounter = document.getElementById('nik-vd-click-counter');
    const successModal = document.getElementById('nik-vd-modal');
    const modalCloseBtn = document.getElementById('nik-vd-modal-close');
    const copyIdBtn = document.getElementById('nik-vd-copy-id-btn');
    const modalTicketId = document.getElementById('nik-vd-modal-ticket-id');
    const modalDept = document.getElementById('nik-vd-modal-dept');
    const modalSummary = document.getElementById('nik-vd-modal-summary');
    const processingIndicator = document.getElementById('nik-vd-processing');
    const noticeEl = document.getElementById('nik-vd-notice');
    const noticeCloseBtn = document.getElementById('nik-vd-notice-close');
    let noticeTimeout = null;

    function showNotification(title, message, isError) {
        if (typeof isError === 'undefined') isError = true;
        let nEl = document.getElementById('nik-vd-notice');
        let titleEl = document.getElementById('nik-vd-notice-title');
        let msgEl = document.getElementById('nik-vd-notice-msg');
        let iconEl = document.getElementById('nik-vd-notice-icon');

        if (!nEl) {
            nEl = document.createElement('div');
            nEl.id = 'nik-vd-notice';
            nEl.className = 'nik-vd-notice' + (isError ? '' : ' nik-vd-notice-success');
            nEl.setAttribute('role', 'alert');
            nEl.innerHTML = '<div class="nik-vd-notice-card">' +
                '<div class="nik-vd-notice-icon" id="nik-vd-notice-icon">' + (isError ? '⚠️' : '✅') + '</div>' +
                '<div class="nik-vd-notice-body">' +
                    '<strong id="nik-vd-notice-title" class="nik-vd-notice-title">' + (title || (isError ? 'Notice' : 'Success')) + '</strong>' +
                    '<p id="nik-vd-notice-msg" class="nik-vd-notice-msg">' + (message || '') + '</p>' +
                '</div>' +
                '<button type="button" id="nik-vd-notice-close" class="nik-vd-notice-close" aria-label="Close">&times;</button>' +
            '</div>';
            document.body.appendChild(nEl);
            const dynClose = nEl.querySelector('#nik-vd-notice-close');
            if (dynClose) dynClose.addEventListener('click', hideNotification);
        } else {
            if (isError) {
                nEl.classList.remove('nik-vd-notice-success');
            } else {
                nEl.classList.add('nik-vd-notice-success');
            }
            if (titleEl) titleEl.innerText = title || (isError ? 'Notice' : 'Success');
            if (msgEl) msgEl.innerText = message || '';
            if (iconEl) iconEl.innerText = isError ? '⚠️' : '✅';

            nEl.classList.remove('nik-vd-hidden');
            nEl.classList.remove('nik-vd-notice-fadeout');
        }

        if (noticeTimeout) clearTimeout(noticeTimeout);
        noticeTimeout = setTimeout(() => {
            hideNotification();
        }, 9000);
    }

    function hideNotification() {
        const nEl = document.getElementById('nik-vd-notice');
        if (!nEl) return;
        nEl.classList.add('nik-vd-notice-fadeout');
        setTimeout(() => {
            nEl.classList.add('nik-vd-hidden');
            nEl.classList.remove('nik-vd-notice-fadeout');
        }, 350);
        if (noticeTimeout) clearTimeout(noticeTimeout);
    }

    if (noticeCloseBtn) {
        noticeCloseBtn.addEventListener('click', hideNotification);
    }

    // Initialize Customer Portal Dark/Light mode with Smart Auto-Detection
    (function initPortalTheme() {
        const portalBtn = document.getElementById('nik-portal-theme-btn');
        const portalRoot = document.getElementById('nik-portal-root');
        const detailBtn = document.getElementById('nik-detail-theme-btn');
        const detailRoot = document.getElementById('nik-detail-root');

        function detectAutoTheme() {
            const saved = localStorage.getItem('nikvotia_portal_theme');
            if (saved === 'dark' || saved === 'light') return saved;

            const darkClasses = ['dark', 'dark-mode', 'theme-dark', 'is-dark-theme', 'wp-dark-mode-active'];
            for (let i = 0; i < darkClasses.length; i++) {
                if (document.body.classList.contains(darkClasses[i]) || document.documentElement.classList.contains(darkClasses[i])) {
                    return 'dark';
                }
            }
            if (document.documentElement.getAttribute('data-theme') === 'dark' || document.body.getAttribute('data-theme') === 'dark' || document.documentElement.getAttribute('data-bs-theme') === 'dark') {
                return 'dark';
            }

            try {
                const bg = window.getComputedStyle(document.body).backgroundColor;
                const rgb = bg.match(/\d+/g);
                if (rgb && rgb.length >= 3) {
                    const lum = (0.299 * parseInt(rgb[0], 10) + 0.587 * parseInt(rgb[1], 10) + 0.114 * parseInt(rgb[2], 10)) / 255;
                    if (lum < 0.45) return 'dark';
                }
            } catch (err) {}

            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            return 'light';
        }

        const activeTheme = detectAutoTheme();

        function applyThemeToRoot(root, btn, isDark) {
            if (!root) return;
            if (isDark) {
                root.classList.add('nik-portal-dark');
            } else {
                root.classList.remove('nik-portal-dark');
            }
            if (btn) {
                const icon = btn.querySelector('.nik-portal-theme-icon') || btn.querySelector('.nik-detail-theme-icon');
                const txt = btn.querySelector('.nik-portal-theme-text') || btn.querySelector('.nik-detail-theme-text');
                if (icon) icon.textContent = isDark ? '☀️' : '🌙';
                if (txt) txt.textContent = isDark ? 'Light Mode' : 'Dark Mode';
            }
        }

        if (portalRoot) {
            applyThemeToRoot(portalRoot, portalBtn, activeTheme === 'dark');
            if (portalBtn) {
                portalBtn.addEventListener('click', function() {
                    const isDark = !portalRoot.classList.contains('nik-portal-dark');
                    applyThemeToRoot(portalRoot, portalBtn, isDark);
                    localStorage.setItem('nikvotia_portal_theme', isDark ? 'dark' : 'light');
                });
            }
        }

        if (detailRoot) {
            applyThemeToRoot(detailRoot, detailBtn, activeTheme === 'dark');
            if (detailBtn) {
                detailBtn.addEventListener('click', function() {
                    const isDark = !detailRoot.classList.contains('nik-portal-dark');
                    applyThemeToRoot(detailRoot, detailBtn, isDark);
                    localStorage.setItem('nikvotia_portal_theme', isDark ? 'dark' : 'light');
                });
            }
        }
    })();

    if (!micBtn) return;

    let mediaRecorder = null;
    let audioChunks = [];
    let clickedElements = [];
    let isRecording = false;
    let recordingStartTime = 0;
    let timerInterval = null;
    let clickCount = 0;

    // Start Recording
    micBtn.addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            startRecordingSession(stream);
        } catch (err) {
            console.error('Error accessing microphone:', err);
            showNotification(
                'Microphone Permission Required',
                'Microphone access is required to record your voice ticket. Please allow microphone access in your browser settings and try again.',
                true
            );
        }
    });

    // Stop and Send
    if (stopBtn) {
        stopBtn.addEventListener('click', () => {
            if (isRecording && mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
        });
    }

    // Cancel Recording
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            if (isRecording) {
                cleanupRecording();
                resetUI();
            }
        });
    }

    // Close Modal
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', () => {
            if (successModal) successModal.classList.add('nik-vd-hidden');
        });
    }

    // Close Modal on backdrop click
    if (successModal) {
        successModal.addEventListener('click', function(e) {
            if (e.target === successModal) {
                successModal.classList.add('nik-vd-hidden');
            }
        });
    }

    // View Ticket Button Navigation Handler
    const viewTicketBtn = document.getElementById('nik-vd-view-tickets-btn');
    if (viewTicketBtn) {
        viewTicketBtn.addEventListener('click', function(e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.which === 2) {
                return; // Allow new-tab / middle click
            }
            const dest = viewTicketBtn.getAttribute('href');
            if (dest && dest !== '#' && dest !== '' && !dest.startsWith('javascript:')) {
                e.preventDefault();
                window.location.href = dest;
            }
        });
    }

    // Success Modal Theme Handler
    function getPreferredModalTheme() {
        const saved = localStorage.getItem('nikvotia_portal_theme');
        if (saved === 'dark' || saved === 'light') return saved;

        // Check if portal is in dark mode
        if (document.querySelector('.nik-portal-dark')) return 'dark';

        // Check theme dark mode classes
        const darkClasses = ['dark', 'dark-mode', 'theme-dark', 'is-dark-theme', 'wp-dark-mode-active'];
        for (let i = 0; i < darkClasses.length; i++) {
            if (document.body.classList.contains(darkClasses[i]) || document.documentElement.classList.contains(darkClasses[i])) {
                return 'dark';
            }
        }
        if (document.documentElement.getAttribute('data-theme') === 'dark' || document.body.getAttribute('data-theme') === 'dark' || document.documentElement.getAttribute('data-bs-theme') === 'dark') {
            return 'dark';
        }

        // Check background luminance of body
        try {
            const bg = window.getComputedStyle(document.body).backgroundColor;
            const rgb = bg.match(/\d+/g);
            if (rgb && rgb.length >= 3) {
                const brightness = (parseInt(rgb[0], 10) * 299 + parseInt(rgb[1], 10) * 587 + parseInt(rgb[2], 10) * 114) / 1000;
                if (brightness < 128) return 'dark';
            }
        } catch (e) {}

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }

        return 'light';
    }

    function applyModalTheme(theme) {
        if (!successModal) return;
        const themeIcon = document.getElementById('nik-vd-modal-theme-icon');
        const themeToggle = document.getElementById('nik-vd-modal-theme-toggle');
        if (theme === 'dark') {
            successModal.classList.add('nik-vd-modal-dark');
            successModal.classList.remove('nik-vd-modal-light');
            if (themeIcon) themeIcon.textContent = '☀️';
            if (themeToggle) themeToggle.setAttribute('title', 'Switch to Light Mode');
        } else {
            successModal.classList.remove('nik-vd-modal-dark');
            successModal.classList.add('nik-vd-modal-light');
            if (themeIcon) themeIcon.textContent = '🌙';
            if (themeToggle) themeToggle.setAttribute('title', 'Switch to Dark Mode');
        }
    }

    // Modal Theme Toggle Button
    const modalThemeBtn = document.getElementById('nik-vd-modal-theme-toggle');
    if (modalThemeBtn && successModal) {
        modalThemeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const willBeDark = !successModal.classList.contains('nik-vd-modal-dark');
            const newTheme = willBeDark ? 'dark' : 'light';
            applyModalTheme(newTheme);
            localStorage.setItem('nikvotia_portal_theme', newTheme);
        });
    }

    // Copy Ticket ID
    if (copyIdBtn) {
        copyIdBtn.addEventListener('click', function() {
            const idText = modalTicketId ? modalTicketId.innerText.replace('#', '').trim() : '';
            if (!idText) return;

            navigator.clipboard.writeText(idText).then(() => {
                const orig = copyIdBtn.innerHTML;
                copyIdBtn.innerHTML = '✓ Copied!';
                copyIdBtn.style.backgroundColor = '#16a34a';
                copyIdBtn.style.color = '#fff';
                setTimeout(() => {
                    copyIdBtn.innerHTML = orig;
                    copyIdBtn.style.backgroundColor = '';
                    copyIdBtn.style.color = '';
                }, 2000);
            });
        });
    }

    // Global Click Tracker during recording
    document.addEventListener('click', function(e) {
        if (!isRecording) return;

        // Ignore clicks on VoiceDesk UI components
        if (e.target.closest('#nik-vd-root') || e.target.closest('#nik-vd-dock') || e.target.closest('#nik-vd-mic-btn') || e.target.closest('#nik-vd-modal')) {
            return;
        }

        // Deactivate links and buttons during recording so clicking them does not navigate away
        e.preventDefault();
        e.stopPropagation();

        clickCount++;
        if (clickCounter) {
            clickCounter.innerText = clickCount + (clickCount === 1 ? ' Click Logged' : ' Clicks Logged');
            clickCounter.classList.remove('nik-vd-hidden');
        }

        // Generate target description
        const target = e.target;
        const tag = target.tagName ? target.tagName.toLowerCase() : 'element';
        let elementText = target.innerText || target.value || target.getAttribute('aria-label') || target.getAttribute('title') || target.getAttribute('alt') || '';
        elementText = elementText.trim().replace(/\s+/g, ' ').substring(0, 50);

        if (target && target.classList) {
            target.classList.add('nik-vd-element-highlighted');
        }

        const selector = getCssSelector(target);
        const timeOffset = Date.now() - recordingStartTime;

        clickedElements.push({
            tag: tag,
            text: elementText,
            selector: selector,
            x: Math.round(e.pageX),
            y: Math.round(e.pageY),
            timeOffset: timeOffset
        });

        // Spawn visual numbered click marker on the page
        spawnClickMarker(e.pageX, e.pageY, clickCount, tag);
    }, true); // Capture phase ensures we always intercept before stopPropagation

    // Prevent form submissions while recording
    document.addEventListener('submit', function(e) {
        if (isRecording && !e.target.closest('#nik-vd-root')) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);

    function startRecordingSession(stream) {
        audioChunks = [];
        clickedElements = [];
        clickCount = 0;
        isRecording = true;
        recordingStartTime = Date.now();

        // Use standard webm with opus codec
        let options = { mimeType: 'audio/webm' };
        if (!MediaRecorder.isTypeSupported('audio/webm')) {
            options = { mimeType: 'audio/ogg' };
        }

        try {
            mediaRecorder = new MediaRecorder(stream, options);
        } catch (e) {
            mediaRecorder = new MediaRecorder(stream);
        }

        mediaRecorder.addEventListener('dataavailable', event => {
            if (event.data && event.data.size > 0) {
                audioChunks.push(event.data);
            }
        });

        mediaRecorder.addEventListener('stop', () => {
            cleanupRecording();
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            sendTicketData(audioBlob);
        });

        mediaRecorder.start(250); // Collect in chunks
        showDock();
        startTimer();
        document.body.classList.add('nik-vd-recording-active');
    }

    function cleanupRecording() {
        isRecording = false;
        clearInterval(timerInterval);
        if (mediaRecorder && mediaRecorder.stream) {
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
        document.body.classList.remove('nik-vd-recording-active');
    }

    function startTimer() {
        let seconds = 0;
        if (timerDisplay) timerDisplay.innerText = '00:00';
        timerInterval = setInterval(() => {
            seconds++;
            const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
            const secs = String(seconds % 60).padStart(2, '0');
            if (timerDisplay) timerDisplay.innerText = mins + ':' + secs;
        }, 1000);
    }

    function showDock() {
        if (dock) dock.classList.remove('nik-vd-hidden');
        const triggerWrap = document.getElementById('nik-vd-trigger-wrap');
        if (triggerWrap) triggerWrap.classList.add('nik-vd-hidden');
        if (micBtn) micBtn.classList.add('nik-vd-hidden');
        const brandingBtn = document.getElementById('nik-vd-branding-btn');
        if (brandingBtn) brandingBtn.classList.add('nik-vd-hidden');
        const attrBar = document.getElementById('nik-vd-attribution-bar');
        if (attrBar) attrBar.classList.remove('nik-vd-hidden');
        if (clickCounter) {
            clickCounter.innerText = '0 Clicks Logged';
            clickCounter.classList.add('nik-vd-hidden');
        }
    }

    function resetUI() {
        if (dock) dock.classList.add('nik-vd-hidden');
        const triggerWrap = document.getElementById('nik-vd-trigger-wrap');
        if (triggerWrap) triggerWrap.classList.remove('nik-vd-hidden');
        if (micBtn) micBtn.classList.remove('nik-vd-hidden');
        const brandingBtn = document.getElementById('nik-vd-branding-btn');
        if (brandingBtn) brandingBtn.classList.remove('nik-vd-hidden');
        if (processingIndicator) processingIndicator.classList.add('nik-vd-hidden');
        const attrBar = document.getElementById('nik-vd-attribution-bar');
        if (attrBar) attrBar.classList.add('nik-vd-hidden');
        removeAllMarkers();
    }

    function spawnClickMarker(x, y, number, tag) {
        const marker = document.createElement('div');
        marker.className = 'nik-vd-click-pin';
        marker.style.left = (x - 14) + 'px';
        marker.style.top = (y - 14) + 'px';
        marker.innerHTML = '<span class="nik-vd-pin-num">' + number + '</span><span class="nik-vd-pin-ripple"></span>';
        document.body.appendChild(marker);

        // Fade after 3.5 seconds
        setTimeout(() => {
            marker.classList.add('nik-vd-pin-fade');
            setTimeout(() => {
                if (marker.parentNode) marker.parentNode.removeChild(marker);
            }, 600);
        }, 3500);
    }

    function removeAllMarkers() {
        const markers = document.querySelectorAll('.nik-vd-click-pin');
        markers.forEach(m => m.remove());
        const highlighted = document.querySelectorAll('.nik-vd-element-highlighted');
        highlighted.forEach(el => el.classList.remove('nik-vd-element-highlighted'));
    }

    async function sendTicketData(audioBlob) {
        if (dock) dock.classList.add('nik-vd-hidden');
        if (processingIndicator) processingIndicator.classList.remove('nik-vd-hidden');
        const attrBar = document.getElementById('nik-vd-attribution-bar');
        if (attrBar) attrBar.classList.add('nik-vd-hidden');

        const formData = new FormData();
        formData.append('audio', audioBlob, 'recording.webm');
        formData.append('page_url', window.location.href);
        formData.append('environment', navigator.userAgent);
        formData.append('clicked_elements', JSON.stringify(clickedElements));

        const apiData = window.nikvotiaData || {};
        const restUrl = apiData.restUrl || (window.location.origin + '/wp-json/nikvotia/v1/submit');
        const headers = {};
        if (apiData.nonce) {
            headers['X-WP-Nonce'] = apiData.nonce;
        }

        try {
            let response = await fetch(restUrl, {
                method: 'POST',
                headers: headers,
                body: formData
            });

            // If 403 invalid nonce occurs (stale page cache or expired nonce), retry without header
            if (response.status === 403 && headers['X-WP-Nonce']) {
                response = await fetch(restUrl, {
                    method: 'POST',
                    body: formData
                });
            }

            const rawResponseText = await response.text();
            let result = null;
            try {
                result = JSON.parse(rawResponseText);
            } catch (jsonErr) {
                // Try extracting JSON object substring if stray notice or comments were output
                const firstBrace = rawResponseText.indexOf('{');
                const lastBrace = rawResponseText.lastIndexOf('}');
                if (firstBrace !== -1 && lastBrace > firstBrace) {
                    try {
                        result = JSON.parse(rawResponseText.substring(firstBrace, lastBrace + 1));
                    } catch (subErr) {
                        console.warn('VoiceDesk: Fallback JSON parse failed:', subErr);
                    }
                }
                if (!result) {
                    console.warn('VoiceDesk: Response was not valid JSON:', rawResponseText);
                }
            }

            if (processingIndicator) processingIndicator.classList.add('nik-vd-hidden');
            const triggerWrap = document.getElementById('nik-vd-trigger-wrap');
            if (triggerWrap) triggerWrap.classList.remove('nik-vd-hidden');
            if (micBtn) micBtn.classList.remove('nik-vd-hidden');

            if (response.ok && result && result.success) {
                // Show professional Success Modal
                if (modalTicketId) modalTicketId.innerText = '#' + result.ticket_number;
                if (modalDept) modalDept.innerText = result.department || 'General Support';
                if (modalSummary && result.summary) modalSummary.innerText = result.summary;

                // Update View My Ticket button link to the direct ticket URL
                const viewTicketBtn = document.getElementById('nik-vd-view-tickets-btn');
                if (viewTicketBtn) {
                    if (result.ticket_url) {
                        viewTicketBtn.href = result.ticket_url;
                    } else if (result.ticket_number) {
                        const basePortal = (result.is_logged_in === false && apiData.viewUrl) ? apiData.viewUrl : (apiData.portalUrl || window.location.href);
                        viewTicketBtn.href = basePortal + (basePortal.indexOf('?') !== -1 ? '&' : '?') + 'ticket=' + encodeURIComponent(result.ticket_number);
                    }
                    if (result.is_logged_in === false) {
                        viewTicketBtn.innerText = 'Sign In to View Ticket';
                    } else {
                        viewTicketBtn.innerText = 'View My Ticket';
                    }
                }

                if (successModal) {
                    applyModalTheme(getPreferredModalTheme());
                    successModal.classList.remove('nik-vd-hidden');
                } else {
                    // Always show success notification if modal is not in DOM
                    showNotification(
                        'Ticket Submitted Successfully!',
                        'Your voice ticket #' + (result.ticket_number || '') + ' was submitted successfully. Department: ' + (result.department || 'General Support'),
                        false
                    );
                }
            } else {
                let errorTitle = 'Ticket Submission Failed';
                let errorMsg = 'There was a problem submitting your voice ticket. Please try again.';

                if (result && result.message) {
                    errorMsg = result.message;
                } else if (result && result.code) {
                    errorMsg = result.code + ': ' + (result.message || 'Server error occurred.');
                } else if (!response.ok) {
                    errorMsg = 'Server returned HTTP error ' + response.status + ' (' + (response.statusText || 'Error') + '). Please try again.';
                }

                showNotification(errorTitle, errorMsg, true);
            }
        } catch (error) {
            console.error('Error sending voice ticket:', error);
            if (processingIndicator) processingIndicator.classList.add('nik-vd-hidden');
            const triggerWrap = document.getElementById('nik-vd-trigger-wrap');
            if (triggerWrap) triggerWrap.classList.remove('nik-vd-hidden');
            if (micBtn) micBtn.classList.remove('nik-vd-hidden');

            const netMsg = error && error.message ? error.message : 'Connection failed';
            showNotification(
                'Connection Error',
                'A network error occurred while submitting your ticket (' + netMsg + '). Please check your internet connection and try again.',
                true
            );
        }

        removeAllMarkers();
    }

    // Helper to generate a unique CSS selector for clicked elements
    function getCssSelector(el) {
        if (!(el instanceof Element)) return 'unknown';
        let path = [];
        while (el && el.nodeType === Node.ELEMENT_NODE) {
            let selector = el.nodeName.toLowerCase();
            if (el.id) {
                selector += '#' + el.id;
                path.unshift(selector);
                break;
            } else {
                let sib = el, nth = 1;
                while (sib = sib.previousElementSibling) {
                    if (sib.nodeName.toLowerCase() === selector) nth++;
                }
                if (nth !== 1) selector += ":nth-of-type(" + nth + ")";
            }
            path.unshift(selector);
            el = el.parentNode;
            if (path.length > 5) break; // Keep selector clean and performant
        }
        return path.join(" > ");
    }
});
