// Voice Recorder - سیستم ضبط و ارسال پیام صوتی









(function() {









    'use strict';

    const voiceLifecycle = window.GroupChatLifecycle || null;
    const createOwnedInterval = (callback, ms) => voiceLifecycle?.interval(callback, ms)
        ?? window.setInterval(callback, ms);
    const clearOwnedInterval = id => {
        if (voiceLifecycle?.clearInterval) voiceLifecycle.clearInterval(id);
        else window.clearInterval(id);
    };

    const notify = (message, type = 'error') => window.GroupChatFeedback?.toast
        ? window.GroupChatFeedback.toast(message, { type })
        : console[type === 'error' ? 'error' : 'info'](message);
    const confirmAction = (message, options = {}) => window.GroupChatFeedback?.confirm
        ? window.GroupChatFeedback.confirm(message, options)
        : Promise.resolve(false);









    









    const groupId = window.groupId || null;









    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;









    









    if (!groupId) {









        console.warn('Voice recorder: groupId not found');









        return;









    }



















    // State management









    let mediaRecorder = null;









    let audioChunks = [];









    let audioStream = null;









    let recordingStartTime = null;









    let recordingTimer = null;

    let stopRecordingButton = null;

    let recordedAudioBlob = null;

    let optimisticVoiceTempId = null;

    let optimisticVoiceBlobUrl = null;









    let isRecording = false;









    let isPaused = false;

    let recordedAudioMimeType = 'audio/webm';









    let audioContext = null;









    let analyser = null;









    let dataArray = null;









    let animationFrame = null;



















    // DOM Elements









    let recordButton = null;









    let recordingModal = null;









    let recordingIndicator = null;









    let recordingTimeDisplay = null;









    let waveformCanvas = null;









    let cancelButton = null;









    let sendButton = null;









    let playButton = null;









    let audioPreview = null;



















    // Initialize




    function init() {




        createRecordButton();




        createRecordingModal();




        setupEventListeners();




    }









    async function safeReadJson(response) {




        const text = await response.text();




        if (!text) return {};









        try {




            return JSON.parse(text);




        } catch (e) {




            const start = text.indexOf('{');




            const end = text.lastIndexOf('}');




            if (start !== -1 && end !== -1 && end > start) {




                try {




                    return JSON.parse(text.slice(start, end + 1));




                } catch (_) {




                    // ignore




                }




            }




            return { status: 'error', message: text.trim() || 'Invalid server response' };




        }




    }














    // Create record button









    function createRecordButton() {









        const chatForm = document.getElementById('chatForm');









        if (!chatForm) {









            setTimeout(createRecordButton, 500);









            return;









        }



















        const submitButton = chatForm.querySelector('button[type="submit"]');









        if (!submitButton) return;



















        // If button already exists, rebind it to this recorder









        const existingButton = document.getElementById('voice-record-btn');









        if (existingButton) {









            const freshButton = existingButton.cloneNode(true);









            existingButton.parentElement.replaceChild(freshButton, existingButton);









            recordButton = freshButton;









            recordButton.onclick = toggleRecording;









            return;









        }



















        recordButton = document.createElement('button');









        recordButton.type = 'button';









        recordButton.id = 'voice-record-btn';









        recordButton.className = 'btn btn-outline-primary rounded-4 d-flex align-items-center justify-content-center';









        recordButton.style.cssText = 'width: 48px; height: 48px; padding: 0;';









        recordButton.innerHTML = '<i class="fas fa-microphone"></i>';









        recordButton.title = 'ضبط پیام صوتی';









        recordButton.onclick = toggleRecording;



















        // Insert before submit button









        submitButton.parentElement.insertBefore(recordButton, submitButton);









    }



















    // Create recording modal









    function createRecordingModal() {









        if (document.getElementById('voice-recording-modal')) return;



















        recordingModal = document.createElement('div');









        recordingModal.id = 'voice-recording-modal';









        recordingModal.style.cssText = `









            position: fixed;









            inset: 0;









            background: rgba(0, 0, 0, 0.7);









            z-index: 10000;









            display: none;









            align-items: center;









            justify-content: center;









            direction: rtl;









        `;



















        recordingModal.innerHTML = `









            <div style="









                background: white;









                border-radius: 20px;









                padding: 2rem;









                max-width: 400px;









                width: 90%;









                box-shadow: 0 20px 60px rgba(0,0,0,0.3);









            ">









                <div style="text-align: center; margin-bottom: 1.5rem;">









                    <h4 style="margin-bottom: 0.5rem; color: #1f2937;">ضبط پیام صوتی</h4>









                    <p style="color: #6b7280; font-size: 0.9rem;">در حال ضبط...</p>









                </div>



















                <div id="recording-indicator" style="









                    display: flex;









                    flex-direction: column;









                    align-items: center;









                    margin-bottom: 1.5rem;









                ">









                    <div id="waveform-container" style="









                        width: 100%;









                        height: 80px;









                        margin-bottom: 1rem;









                        background: #f3f4f6;









                        border-radius: 10px;









                        display: flex;









                        align-items: center;









                        justify-content: center;









                    ">









                        <canvas id="waveform-canvas" width="360" height="80" style="width: 100%; height: 100%;"></canvas>









                    </div>









                    









                    <div id="recording-time" style="









                        font-size: 2rem;









                        font-weight: bold;









                        color: #ef4444;









                        font-family: 'Courier New', monospace;









                    ">00:00</div>









                </div>



















                <div id="recording-controls" style="









                    display: flex;









                    gap: 1rem;









                    justify-content: center;









                    margin-bottom: 1rem;









                ">









                    <button id="pause-resume-btn" type="button" style="









                        padding: 0.75rem 1.5rem;









                        border: none;









                        border-radius: 10px;









                        background: #f3f4f6;









                        color: #374151;









                        cursor: pointer;









                        font-weight: 500;









                    ">









                        <i class="fas fa-pause"></i> توقف موقت









                    </button>









                    <button id="stop-recording-btn" type="button" style="









                        padding: 0.75rem 1.5rem;









                        border: none;









                        border-radius: 10px;









                        background: #ef4444;









                        color: white;









                        cursor: pointer;









                        font-weight: 500;









                    ">









                        <i class="fas fa-stop"></i> توقف









                    </button>









                </div>



















                <div style="









                    display: flex;









                    gap: 1rem;









                    justify-content: center;









                ">









                    <button id="cancel-recording-btn" type="button" style="









                        padding: 0.75rem 1.5rem;









                        border: 2px solid #ef4444;









                        border-radius: 10px;









                        background: white;









                        color: #ef4444;









                        cursor: pointer;









                        font-weight: 500;









                    ">









                        <i class="fas fa-times"></i> لغو









                    </button>









                    <button id="send-recording-btn" type="button" style="









                        padding: 0.75rem 1.5rem;









                        border: none;









                        border-radius: 10px;









                        background: #10b981;









                        color: white;









                        cursor: pointer;









                        font-weight: 500;









                        display: none;









                    ">









                        <i class="fas fa-paper-plane"></i> ارسال









                    </button>









                </div>



















                <div id="audio-preview-container" style="









                    margin-top: 1.5rem;









                    padding-top: 1.5rem;









                    border-top: 1px solid #e5e7eb;









                    display: none;









                ">









                    <p style="margin-bottom: 0.5rem; color: #6b7280; font-size: 0.9rem;">پیش‌نمایش:</p>









                    <audio id="audio-preview" controls style="width: 100%;"></audio>









                </div>









            </div>









        `;



















        document.body.appendChild(recordingModal);



















        // Get references









        recordingTimeDisplay = document.getElementById('recording-time');









        waveformCanvas = document.getElementById('waveform-canvas');









        cancelButton = document.getElementById('cancel-recording-btn');









        sendButton = document.getElementById('send-recording-btn');









        playButton = document.getElementById('pause-resume-btn');









        const stopButton = document.getElementById('stop-recording-btn');









        audioPreview = document.getElementById('audio-preview');









        









        // Store stop button reference









        stopRecordingButton = stopButton;









    }



















    // Setup event listeners









    function setupEventListeners() {









        setTimeout(() => {









            if (cancelButton) {









                cancelButton.onclick = cancelRecording;









            }









            if (sendButton) {









                sendButton.onclick = sendRecording;









            }









            if (playButton) {









                playButton.onclick = togglePauseResume;









            }









            if (stopRecordingButton) {









                stopRecordingButton.onclick = stopRecording;









            }









        }, 500);









    }



















    // Toggle recording









    async function toggleRecording() {









        if (!isRecording) {









            await startRecording();









        } else {









            stopRecording();









        }









    }



















    // Start recording









    async function startRecording() {









        try {









            // Request microphone access









            audioStream = await navigator.mediaDevices.getUserMedia({ 









                audio: {









                    echoCancellation: true,









                    noiseSuppression: true,









                    autoGainControl: true









                } 









            });



















            // Initialize audio context for waveform









            audioContext = new (window.AudioContext || window.webkitAudioContext)();









            analyser = audioContext.createAnalyser();









            const source = audioContext.createMediaStreamSource(audioStream);









            source.connect(analyser);









            analyser.fftSize = 256;









            dataArray = new Uint8Array(analyser.frequencyBinCount);



















            // Setup MediaRecorder









            const options = {









                mimeType: 'audio/webm;codecs=opus',









                audioBitsPerSecond: 128000









            };



















            // Fallback for browsers that don't support webm









            if (!MediaRecorder.isTypeSupported(options.mimeType)) {









                if (MediaRecorder.isTypeSupported('audio/webm')) {









                    options.mimeType = 'audio/webm';









                } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {









                    options.mimeType = 'audio/ogg;codecs=opus';









                } else if (MediaRecorder.isTypeSupported('audio/ogg')) {









                    options.mimeType = 'audio/ogg';









                } else {









                    options.mimeType = 'audio/webm'; // Default









                }









            }



















            mediaRecorder = new MediaRecorder(audioStream, options);
            recordedAudioMimeType = mediaRecorder.mimeType || options.mimeType || 'audio/webm';









            audioChunks = [];



















            mediaRecorder.ondataavailable = (event) => {









                if (event.data.size > 0) {









                    audioChunks.push(event.data);









                }









            };



















            mediaRecorder.onstop = () => {









                handleRecordingStop();









            };



















            mediaRecorder.onerror = (event) => {









                console.error('MediaRecorder error:', event);









                showError('خطا در ضبط صدا. لطفاً دوباره تلاش کنید.');









            };



















            // Start recording









            mediaRecorder.start(100); // Collect data every 100ms









            isRecording = true;









            recordingStartTime = Date.now();



















            // Show modal









            showRecordingModal();



















            // Start timer









            startTimer();



















            // Start waveform visualization









            startWaveformVisualization();



















            // Update button









            if (recordButton) {









                recordButton.innerHTML = '<i class="fas fa-stop"></i>';









                recordButton.style.background = '#ef4444';









                recordButton.style.color = 'white';









            }



















        } catch (error) {









            console.error('Error starting recording:', error);









            if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {









                showError('دسترسی به میکروفون داده نشد. لطفاً در تنظیمات مرورگر اجازه دهید.');









            } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {









                showError('میکروفون یافت نشد. لطفاً یک میکروفون متصل کنید.');









            } else {









                showError('خطا در دسترسی به میکروفون: ' + error.message);









            }









        }









    }



















    // Stop recording









    function stopRecording() {









        if (mediaRecorder && isRecording) {









            mediaRecorder.stop();









            isRecording = false;









            isPaused = false;



















            // Stop all tracks









            if (audioStream) {









                audioStream.getTracks().forEach(track => track.stop());









            }



















            // Stop timer









            stopTimer();



















            // Stop waveform









            stopWaveformVisualization();



















            // Update button









            if (recordButton) {









                recordButton.innerHTML = '<i class="fas fa-microphone"></i>';









                recordButton.style.background = '';









                recordButton.style.color = '';









            }



















            // Update UI









            if (playButton) {









                playButton.style.display = 'none';









            }









            if (stopRecordingButton) {









                stopRecordingButton.style.display = 'none';









            }









            if (sendButton) {









                sendButton.style.display = 'inline-flex';









            }









        }









    }



















    // Toggle pause/resume









    function togglePauseResume() {









        if (!mediaRecorder || !isRecording) return;



















        if (isPaused) {









            mediaRecorder.resume();









            isPaused = false;









            playButton.innerHTML = '<i class="fas fa-pause"></i> توقف موقت';









            startTimer();









            startWaveformVisualization();









        } else {









            mediaRecorder.pause();









            isPaused = true;









            playButton.innerHTML = '<i class="fas fa-play"></i> ادامه';









            stopTimer();









            stopWaveformVisualization();









        }









    }



















    // Cancel recording









    async function cancelRecording() {









        if (await confirmAction('آیا مطمئن هستید که می‌خواهید ضبط را لغو کنید؟')) {









            stopRecording();









            audioChunks = [];









            hideRecordingModal();









            resetUI();









        }









    }



















    // Handle recording stop









    function handleRecordingStop() {









        if (audioChunks.length === 0) {









            showError('هیچ صدایی ضبط نشد.');









            hideRecordingModal();









            resetUI();









            return;









        }



















        // Create audio blob









        const finalMimeType = recordedAudioMimeType || (mediaRecorder && mediaRecorder.mimeType) || 'audio/webm';
        const audioBlob = new Blob(audioChunks, { type: finalMimeType });









        









        // Show preview









        const audioUrl = URL.createObjectURL(audioBlob);









        if (audioPreview) {









            audioPreview.src = audioUrl;









            const previewContainer = document.getElementById('audio-preview-container');









            if (previewContainer) {









                previewContainer.style.display = 'block';









            }









        }



















        // Store blob for sending









        recordedAudioBlob = audioBlob;









    }



















    // Send recording









    async function sendRecording() {









        if (!recordedAudioBlob) {









            showError('فایل صوتی یافت نشد.');









            return;









        }



















        // Check duration (max 5 minutes)









        const duration = getRecordingDuration();









        if (duration > 300) {









            showError('مدت زمان ضبط نمی‌تواند بیشتر از 5 دقیقه باشد.');









            return;









        }



















        // Show loading









        if (sendButton) {









            sendButton.disabled = true;









            sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ارسال...';









        }



















        try {









            // Create FormData









            const formData = new FormData();









            const blobMimeType = String(recordedAudioBlob.type || recordedAudioMimeType || 'audio/webm').toLowerCase();
            let extension = 'webm';
            if (blobMimeType.includes('ogg')) {
                extension = 'ogg';
            } else if (blobMimeType.includes('wav')) {
                extension = 'wav';
            } else if (blobMimeType.includes('mpeg') || blobMimeType.includes('mp3')) {
                extension = 'mp3';
            } else if (blobMimeType.includes('mp4') || blobMimeType.includes('m4a') || blobMimeType.includes('aac')) {
                extension = 'm4a';
            }

            formData.append('voice_message', recordedAudioBlob, `voice_${Date.now()}.${extension}`);









            formData.append('group_id', groupId);









            formData.append('message', '🎤 پیام صوتی');

            const composerReply = window.GroupChat?.store?.getState?.().composerReply || null;
            if (composerReply?.id && document.getElementById('msg-' + composerReply.id)) {
                formData.append('parent_id', composerReply.id);
            }









            formData.append('_token', csrfToken);



















            // Send to server









            const response = await fetch('/messages/send', {









                method: 'POST',









                body: formData,









                headers: {









                    'X-CSRF-TOKEN': csrfToken,









                    'Accept': 'application/json',









                    'X-Requested-With': 'XMLHttpRequest'









                }









            });



















            // Check if response is OK









            if (!response.ok) {









                const contentType = response.headers.get('content-type');









                let errorMessage = 'خطا در ارسال پیام صوتی.';









                









                if (contentType && contentType.includes('application/json')) {




                    try {




                        const errorJson = await safeReadJson(response);




                        errorMessage = errorJson.message || errorJson.error || errorMessage;









                        









                        // اگر validation errors وجود دارد









                        if (errorJson.errors) {









                            const firstError = Object.values(errorJson.errors)[0];









                            if (Array.isArray(firstError) && firstError.length > 0) {









                                errorMessage = firstError[0];









                            } else if (typeof firstError === 'string') {









                                errorMessage = firstError;









                            }









                        }









                        









                        // اگر message وجود دارد، از آن استفاده کن









                        if (errorJson.message && typeof errorJson.message === 'string') {









                            errorMessage = errorJson.message;









                        }









                    } catch (e) {









                        console.error('Error parsing JSON:', e);









                        errorMessage = `خطا ${response.status}: ${response.statusText}`;









                    }









                } else {









                    // اگر HTML برگردانده شده (مثلاً صفحه خطا)









                    const errorText = await response.text();









                    console.error('Server returned HTML instead of JSON:', errorText.substring(0, 200));









                    errorMessage = 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.';









                }









                









                throw new Error(errorMessage);









            }



















            const result = await safeReadJson(response);














            if (result.status === 'success') {









                // Success









                hideRecordingModal();









                resetUI();









                









                // Clear blob









                if (recordedAudioBlob) {









                    URL.revokeObjectURL(URL.createObjectURL(recordedAudioBlob));









                    recordedAudioBlob = null;









                }



















                // Reload messages or append new message




                if (result.message && typeof renderVoiceMessage === 'function') {




                    try {




                        clearOptimisticVoice(result.message);
                        renderVoiceMessage(result.message, 'voice-submit-response');
                        window.GroupChat?.composer?.cancelReply?.();




                    } catch (renderError) {




                        // Message is already saved on server; keep UX stable and let polling sync it.




                        console.warn('Canonical voice render failed after upload; waiting for polling sync.', renderError);




                    }




                } else {




                    // No hard reload fallback: polling (if enabled) will pick new message.




                    console.warn('Stored voice response is unavailable; waiting for polling sync.');




                }




            } else {









                showError(result.message || 'خطا در ارسال پیام صوتی.');









                if (sendButton) {









                    sendButton.disabled = false;









                    sendButton.innerHTML = '<i class="fas fa-paper-plane"></i> ارسال';









                }









            }









        } catch (error) {









            console.error('Error sending recording:', error);









            const errorMessage = error.message || 'خطا در ارسال پیام صوتی. لطفاً دوباره تلاش کنید.';









            showError(errorMessage);









            if (sendButton) {









                sendButton.disabled = false;









                sendButton.innerHTML = '<i class="fas fa-paper-plane"></i> ارسال';









            }









        }









    }



















    // Show recording modal









    function showRecordingModal() {









        if (recordingModal) {









            recordingModal.style.display = 'flex';









        }









    }



















    // Hide recording modal









    function hideRecordingModal() {









        if (recordingModal) {









            recordingModal.style.display = 'none';









        }









    }



















    // Start timer









    function startTimer() {









        if (recordingTimer) clearOwnedInterval(recordingTimer);









        









        recordingTimer = createOwnedInterval(() => {









            if (recordingTimeDisplay && recordingStartTime) {









                const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);









                const minutes = Math.floor(elapsed / 60);









                const seconds = elapsed % 60;









                recordingTimeDisplay.textContent = 









                    `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;









                









                // Warn at 4:30









                if (elapsed >= 270 && elapsed < 271) {









                    recordingTimeDisplay.style.color = '#f59e0b';









                }









                









                // Stop at 5 minutes









                if (elapsed >= 300) {









                    stopRecording();









                    showError('حداکثر مدت زمان ضبط (5 دقیقه) به پایان رسید.');









                }









            }









        }, 1000);









    }



















    // Stop timer









    function stopTimer() {









        if (recordingTimer) {









            clearOwnedInterval(recordingTimer);









            recordingTimer = null;









        }









    }



















    // Get recording duration









    function getRecordingDuration() {









        if (!recordingStartTime) return 0;









        return Math.floor((Date.now() - recordingStartTime) / 1000);









    }



















    // Start waveform visualization









    function startWaveformVisualization() {









        if (!waveformCanvas || !analyser) return;



















        const canvas = waveformCanvas;









        const ctx = canvas.getContext('2d');









        const width = canvas.width;









        const height = canvas.height;



















        function draw() {









            if (!isRecording || isPaused) return;



















            analyser.getByteFrequencyData(dataArray);



















            ctx.fillStyle = '#f3f4f6';









            ctx.fillRect(0, 0, width, height);



















            const barWidth = width / dataArray.length * 2.5;









            let x = 0;



















            for (let i = 0; i < dataArray.length; i++) {









                const barHeight = (dataArray[i] / 255) * height * 0.8;









                









                const gradient = ctx.createLinearGradient(0, height - barHeight, 0, height);









                gradient.addColorStop(0, '#ef4444');









                gradient.addColorStop(1, '#f87171');









                









                ctx.fillStyle = gradient;









                ctx.fillRect(x, height - barHeight, barWidth - 2, barHeight);









                









                x += barWidth;









            }



















            animationFrame = requestAnimationFrame(draw);









        }



















        draw();









    }



















    // Stop waveform visualization









    function stopWaveformVisualization() {









        if (animationFrame) {









            cancelAnimationFrame(animationFrame);









            animationFrame = null;









        }









        









        if (waveformCanvas) {









            const ctx = waveformCanvas.getContext('2d');









            ctx.clearRect(0, 0, waveformCanvas.width, waveformCanvas.height);









        }









    }



















    // Reset UI









    function resetUI() {









        isRecording = false;









        isPaused = false;









        recordingStartTime = null;









        audioChunks = [];









        









        if (recordingTimeDisplay) {









            recordingTimeDisplay.textContent = '00:00';









            recordingTimeDisplay.style.color = '#ef4444';









        }









        









        if (sendButton) {









            sendButton.style.display = 'none';









            sendButton.disabled = false;









            sendButton.innerHTML = '<i class="fas fa-paper-plane"></i> ارسال';









        }









        









        if (playButton) {









            playButton.style.display = 'inline-flex';









            playButton.innerHTML = '<i class="fas fa-pause"></i> توقف موقت';









        }









        









        if (stopRecordingButton) {









            stopRecordingButton.style.display = 'inline-flex';









        }









        









        const previewContainer = document.getElementById('audio-preview-container');









        if (previewContainer) {









            previewContainer.style.display = 'none';









        }









        









        if (audioPreview) {









            audioPreview.src = '';









        }









    }



















    // Show error









    function showError(message) {









        notify(message);









    }



















    function clearOptimisticVoice(message) {
        const isStoredVoice = (message?.voice_message || message?.voice_message_url)
            && message?.id && !String(message.id).startsWith('voice_temp_');
        if (!isStoredVoice || !optimisticVoiceTempId) return;
        document.getElementById('msg-' + optimisticVoiceTempId)?.remove();
        try { URL.revokeObjectURL(optimisticVoiceBlobUrl); } catch (error) {}
        optimisticVoiceTempId = null;
        optimisticVoiceBlobUrl = null;
    }

    function renderVoiceMessage(message, source) {
        if (!message) return false;
        const canonicalFeed = window.GroupChat?.feed;
        if (typeof canonicalFeed?.apply === 'function') {
            const [rendered] = canonicalFeed.apply([{ ...message, content_type: 'message' }], source);
            return rendered || document.getElementById('msg-' + message.id) || false;
        }
        const legacyRender = window.renderMessageThroughPipeline || window.appendMessage;
        return typeof legacyRender === 'function' ? legacyRender(message, source) : false;
    }

    function installOptimisticVoiceBridge() {
        const originalAppend = window.appendMessage;
        let wrappedAppend = null;

        const clearOptimisticVoice = message => {
            if (!message?.voice_message || !message.id || String(message.id).startsWith('voice_temp_') || !optimisticVoiceTempId) return;
            document.getElementById('msg-' + optimisticVoiceTempId)?.remove();
            try { URL.revokeObjectURL(optimisticVoiceBlobUrl); } catch (error) {}
            optimisticVoiceTempId = null;
            optimisticVoiceBlobUrl = null;
        };

        if (typeof originalAppend === 'function') {
            wrappedAppend = message => {
                clearOptimisticVoice(message);
                return originalAppend(message);
            };
            window.appendMessage = wrappedAppend;
        }

        const handleSendCapture = event => {
            const button = event.target.closest?.('#send-recording-btn');
            const blob = recordedAudioBlob;
            if (!button || !blob) return;
            const blobUrl = URL.createObjectURL(blob);
            const tempId = 'voice_temp_' + Date.now();
            const composerReply = window.GroupChat?.store?.getState?.().composerReply || null;
            const modal = document.getElementById('voice-recording-modal');
            if (modal) {
                modal.style.opacity = '0';
                modal.style.transition = 'opacity 0.2s';
                setTimeout(() => { modal.style.display = 'none'; modal.style.opacity = ''; }, 200);
            }
            const renderVoice = renderVoiceMessage;
            if (typeof renderVoice === 'function') {
                renderVoice({
                    id: tempId,
                    user_id: window.authUserId || 0,
                    message: '🎤 پیام صوتی',
                    created_at: new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' }),
                    sender: 'شما',
                    voice_message: blobUrl,
                    file_type: blob.type || 'audio/webm',
                    parent_id: composerReply?.id || null,
                    parent_sender: composerReply?.sender || '',
                    parent_content: composerReply?.content || '',
                    _isOptimistic: true,
                }, 'voice-optimistic');
            }
            optimisticVoiceTempId = tempId;
            optimisticVoiceBlobUrl = blobUrl;
        };

        if (voiceLifecycle) voiceLifecycle.on(document, 'click', handleSendCapture, true);
        else document.addEventListener('click', handleSendCapture, true);
        return () => {
            document.removeEventListener('click', handleSendCapture, true);
            if (wrappedAppend && window.appendMessage === wrappedAppend) window.appendMessage = originalAppend;
            if (optimisticVoiceBlobUrl) URL.revokeObjectURL(optimisticVoiceBlobUrl);
            optimisticVoiceTempId = null;
            optimisticVoiceBlobUrl = null;
        };
    }

    const disposeOptimisticVoiceBridge = installOptimisticVoiceBridge();

    let recorderDestroyed = false;
    function destroyVoiceRecorder() {
        if (recorderDestroyed) return;
        recorderDestroyed = true;
        disposeOptimisticVoiceBridge();
        stopTimer();

        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            try { mediaRecorder.stop(); } catch (error) {}
        }
        if (audioStream) audioStream.getTracks().forEach(track => track.stop());
        audioStream = null;
        if (audioContext && audioContext.state !== 'closed') {
            Promise.resolve(audioContext.close()).catch(() => {});
        }
        audioContext = null;
        analyser = null;
        dataArray = null;
        recordedAudioBlob = null;
    }

    voiceLifecycle?.add(destroyVoiceRecorder);

    window.GroupVoiceRecorder = Object.freeze({
        start: startRecording,
        stop: stopRecording,
        cancel: cancelRecording,
        send: sendRecording,
        open: showRecordingModal,
        close: hideRecordingModal,
        reset: resetUI,
        getBlob: () => recordedAudioBlob || null,
        destroy: destroyVoiceRecorder,
    });

    // Cleanup on page unload









    voiceLifecycle?.on(window, 'beforeunload', () => {

        destroyVoiceRecorder();









        if (audioStream) {









            audioStream.getTracks().forEach(track => track.stop());









        }









        if (audioContext) {









            audioContext.close();









        }









    });



















    // Initialize when DOM is ready









    if (document.readyState === 'loading') {









        if (voiceLifecycle) voiceLifecycle.on(document, 'DOMContentLoaded', init, { once: true });
        else document.addEventListener('DOMContentLoaded', init, { once: true });









    } else {









        if (voiceLifecycle) voiceLifecycle.timeout(init, 500);
        else setTimeout(init, 500);









    }



















})();



















