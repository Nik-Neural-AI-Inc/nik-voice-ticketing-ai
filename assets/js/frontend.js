document.addEventListener('DOMContentLoaded', function() {
    const micBtn = document.getElementById('nik-vd-mic-btn');
    const overlay = document.getElementById('nik-vd-overlay');
    const stopBtn = document.getElementById('nik-vd-stop-btn');
    const statusMsg = document.getElementById('nik-vd-status');
    const tooltip = document.getElementById('nik-vd-tooltip');
    const pulse = document.getElementById('nik-vd-pulse');
    
    if (!micBtn) return;

    let mediaRecorder;
    let audioChunks = [];
    let clickedElements = [];
    let isRecording = false;
    let recordingStartTime = 0;

    micBtn.addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            startRecording(stream);
        } catch (err) {
            console.error('Error accessing microphone:', err);
            alert('Microphone access is required to use VoiceDesk.');
        }
    });

    stopBtn.addEventListener('click', () => {
        if (isRecording && mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
    });

    // Track clicks on the document while recording
    document.addEventListener('click', (e) => {
        if (!isRecording) return;
        
        // Don't track clicks on our own UI
        if (e.target.closest('#nik-vd-overlay') || e.target.closest('#nik-vd-mic-btn')) {
            return;
        }

        const selector = getCssSelector(e.target);
        clickedElements.push({
            selector: selector,
            x: e.clientX,
            y: e.clientY,
            timeOffset: Date.now() - recordingStartTime
        });
    }, true); // Use capture phase

    function startRecording(stream) {
        audioChunks = [];
        clickedElements = [];
        isRecording = true;
        recordingStartTime = Date.now();

        mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });

        mediaRecorder.addEventListener('dataavailable', event => {
            if (event.data.size > 0) {
                audioChunks.push(event.data);
            }
        });

        mediaRecorder.addEventListener('stop', () => {
            isRecording = false;
            stream.getTracks().forEach(track => track.stop());
            
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            sendData(audioBlob);
        });

        mediaRecorder.start();
        showOverlay();
    }

    function showOverlay() {
        overlay.classList.remove('nik-vd-hidden');
        statusMsg.classList.add('nik-vd-hidden');
        tooltip.classList.remove('nik-vd-hidden');
        pulse.classList.remove('nik-vd-hidden');
        stopBtn.classList.remove('nik-vd-hidden');
    }

    function showProcessing() {
        tooltip.classList.add('nik-vd-hidden');
        pulse.classList.add('nik-vd-hidden');
        stopBtn.classList.add('nik-vd-hidden');
        statusMsg.innerText = nikVoiceDeskData.strings.processing;
        statusMsg.classList.remove('nik-vd-hidden');
    }

    async function sendData(audioBlob) {
        showProcessing();

        const formData = new FormData();
        formData.append('audio', audioBlob, 'recording.webm');
        formData.append('page_url', window.location.href);
        const ua = navigator.userAgent;
        let browserName = "Unknown Browser";
        if (ua.indexOf("Firefox") > -1) browserName = "Firefox";
        else if (ua.indexOf("Edg") > -1) browserName = "Edge";
        else if (ua.indexOf("Chrome") > -1) browserName = "Chrome";
        else if (ua.indexOf("Safari") > -1) browserName = "Safari";

        let osName = "Unknown OS";
        if (ua.indexOf("Win") > -1) osName = "Windows";
        else if (ua.indexOf("Mac") > -1) osName = "MacOS";
        else if (ua.indexOf("Linux") > -1) osName = "Linux";
        else if (ua.indexOf("Android") > -1) osName = "Android";
        else if (ua.indexOf("like Mac") > -1) osName = "iOS";

        formData.append('environment', osName + ' | ' + browserName);
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

            if (response.ok) {
                statusMsg.innerText = nikVoiceDeskData.strings.success + (result.ticket_number || '');
                setTimeout(() => {
                    overlay.classList.add('nik-vd-hidden');
                }, 2000);
            } else {
                statusMsg.innerText = result.message || nikVoiceDeskData.strings.error;
                setTimeout(() => {
                    overlay.classList.add('nik-vd-hidden');
                }, 4000);
            }
        } catch (error) {
            console.error('Error sending VoiceDesk data:', error);
            statusMsg.innerText = nikVoiceDeskData.strings.error;
            setTimeout(() => {
                overlay.classList.add('nik-vd-hidden');
            }, 3000);
        }
    }

    // Helper to generate a unique CSS selector for clicked elements
    function getCssSelector(el) {
        if (!(el instanceof Element)) return;
        let path = [];
        while (el.nodeType === Node.ELEMENT_NODE) {
            let selector = el.nodeName.toLowerCase();
            if (el.id) {
                selector += '#' + el.id;
                path.unshift(selector);
                break;
            } else {
                let sib = el, nth = 1;
                while (sib = sib.previousElementSibling) {
                    if (sib.nodeName.toLowerCase() == selector)
                       nth++;
                }
                if (nth != 1)
                    selector += ":nth-of-type("+nth+")";
            }
            path.unshift(selector);
            el = el.parentNode;
        }
        return path.join(" > ");
    }
});
