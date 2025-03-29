/**
 * Enhanced Text-to-Speech Features for Flashcard App
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize TTS features
    initTextToSpeech();
    
    // Add event listeners for feedback container - TTS button will be added when feedback is displayed
    document.addEventListener('feedbackDisplayed', function(event) {
        // Add text-to-speech button to feedback
        const feedbackContainer = document.getElementById('feedbackContainer');
        const ttsButtonTemplate = document.getElementById('ttsButtonTemplate');
        
        // Check if TTS button already exists
        if (!feedbackContainer.querySelector('#readFeedbackBtn')) {
            // Clone the template and append it
            const ttsButton = ttsButtonTemplate.content.cloneNode(true);
            feedbackContainer.appendChild(ttsButton);
            
            // Add event listener to the button
            document.getElementById('readFeedbackBtn').addEventListener('click', function() {
                readFeedbackAloud();
            });
        }
    });
});

/**
 * Available voices for speech synthesis
 */
let availableVoices = [];

/**
 * Initialize text-to-speech
 */
function initTextToSpeech() {
    // Check if browser supports speech synthesis
    if (!('speechSynthesis' in window)) {
        console.log('Text-to-speech not supported');
        return;
    }
    
    // Load available voices
    loadVoices();
    
    // Some browsers (like Chrome) load voices asynchronously
    window.speechSynthesis.onvoiceschanged = loadVoices;
    
    // Make stopSpeech globally available
    window.stopSpeech = stopSpeech;
}

/**
 * Load available voices and select best quality ones
 */
function loadVoices() {
    // Get all available voices
    availableVoices = window.speechSynthesis.getVoices();
    
    // Log available voices to console (for debugging)
    console.log('Available voices:', availableVoices);
}

/**
 * Get the best available voice based on quality and language
 * @param {string} lang - Preferred language code (e.g., 'en-US')
 * @returns {SpeechSynthesisVoice} The best available voice
 */
function getBestVoice(lang = 'en-US') {
    if (availableVoices.length === 0) {
        return null;
    }
    
    // Priority order for voice selection:
    // 1. Premium/enhanced voices in the specified language
    // 2. Any voice in the specified language
    // 3. Default voice
    
    // Look for premium voices first (naming conventions vary by platform)
    // These are typically higher quality voices
    const premiumVoiceKeywords = ['premium', 'enhanced', 'neural', 'wavenet', 'natural'];
    
    // Try to find a premium voice in the preferred language
    for (const keyword of premiumVoiceKeywords) {
        const premiumVoice = availableVoices.find(voice => 
            voice.lang.startsWith(lang.split('-')[0]) && 
            (voice.name.toLowerCase().includes(keyword) || voice.voiceURI.toLowerCase().includes(keyword))
        );
        
        if (premiumVoice) {
            return premiumVoice;
        }
    }
    
    // If no premium voice, find any voice in the preferred language
    const langVoice = availableVoices.find(voice => voice.lang.startsWith(lang.split('-')[0]));
    if (langVoice) {
        return langVoice;
    }
    
    // If no matching language, return the first available voice
    return availableVoices[0];
}

/**
 * Read feedback aloud using text-to-speech
 */
function readFeedbackAloud() {
    // Check if browser supports speech synthesis
    if (!('speechSynthesis' in window)) {
        alert('Text-to-speech is not supported in your browser.');
        return;
    }
    
    // Get feedback content
    const feedbackContainer = document.getElementById('feedbackContainer');
    const feedbackCard = feedbackContainer.querySelector('.card');
    const feedbackTitle = feedbackCard.querySelector('.card-header').textContent;
    const feedbackText = feedbackCard.querySelector('.card-text').textContent;
    
    // Combine text to read
    let textToRead = feedbackTitle + '. ' + feedbackText;
    
    // Get missing points if any
    const missingPoints = feedbackCard.querySelector('.missing-points');
    if (missingPoints) {
        textToRead += ' Key points to remember: ';
        const points = missingPoints.querySelectorAll('li');
        points.forEach((point, index) => {
            textToRead += 'Point ' + (index + 1) + ': ' + point.textContent + '. ';
        });
    }
    
    // Create utterance
    const utterance = new SpeechSynthesisUtterance(textToRead);
    
    // Get best voice based on browser language
    const preferredLanguage = navigator.language || 'en-US';
    const bestVoice = getBestVoice(preferredLanguage);
    
    // Set properties
    if (bestVoice) {
        utterance.voice = bestVoice;
    }
    utterance.lang = preferredLanguage;
    utterance.rate = 1.0;
    utterance.pitch = 1.0;
    utterance.volume = 1.0;
    
    // Get read feedback button
    const readFeedbackBtn = document.getElementById('readFeedbackBtn');
    
    // Events
    utterance.onstart = function() {
        readFeedbackBtn.classList.add('speaking');
        readFeedbackBtn.innerHTML = '<i class="bi bi-pause-fill me-1"></i> Pause';
    };
    
    utterance.onend = function() {
        readFeedbackBtn.classList.remove('speaking');
        readFeedbackBtn.innerHTML = '<i class="bi bi-volume-up me-1"></i> Read Feedback Aloud';
    };
    
    utterance.onerror = function(event) {
        console.error('Speech synthesis error', event);
        readFeedbackBtn.classList.remove('speaking');
        readFeedbackBtn.innerHTML = '<i class="bi bi-volume-up me-1"></i> Read Feedback Aloud';
    };
    
    // Toggle between play and pause
    if (window.speechSynthesis.speaking) {
        if (window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
            readFeedbackBtn.innerHTML = '<i class="bi bi-pause-fill me-1"></i> Pause';
            readFeedbackBtn.classList.add('speaking');
        } else {
            window.speechSynthesis.pause();
            readFeedbackBtn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Resume';
            readFeedbackBtn.classList.remove('speaking');
        }
    } else {
        // Start new speech
        window.speechSynthesis.speak(utterance);
    }
}

/**
 * Stop any ongoing text-to-speech
 */
function stopSpeech() {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        
        const readFeedbackBtn = document.getElementById('readFeedbackBtn');
        if (readFeedbackBtn) {
            readFeedbackBtn.classList.remove('speaking');
            readFeedbackBtn.innerHTML = '<i class="bi bi-volume-up me-1"></i> Read Feedback Aloud';
        }
    }
}

// Stop speech when navigating away
window.addEventListener('beforeunload', function() {
    stopSpeech();
});