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
            alert('Microphone access is required to record a voice ticket. Please grant permission in your browser.');
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

        try {
            const response = await fetch(nikVoiceDeskData.restUrl, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': nikVoiceDeskData.nonce
                },
                body: formData
            });

            const result = await response.json();

            if (processingIndicator) processingIndicator.classList.add('nik-vd-hidden');
            if (micBtn) micBtn.classList.remove('nik-vd-hidden');

            if (response.ok && result.success) {
                // Show professional Success Modal
                if (modalTicketId) modalTicketId.innerText = '#' + result.ticket_number;
                if (modalDept) modalDept.innerText = result.department || 'General Support';
                if (modalSummary && result.summary) modalSummary.innerText = result.summary;

                if (successModal) {
                    successModal.classList.remove('nik-vd-hidden');
                }
            } else {
                alert(result.message || 'There was a problem submitting your voice ticket. Please try again.');
            }
        } catch (error) {
            console.error('Error sending voice ticket:', error);
            if (processingIndicator) processingIndicator.classList.add('nik-vd-hidden');
            if (micBtn) micBtn.classList.remove('nik-vd-hidden');
            alert('A network error occurred while submitting your ticket. Please try again.');
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
